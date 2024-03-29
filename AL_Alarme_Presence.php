<?php
/************************************************************************************************************************
AL_Alarme Presence	 - 65
Si le lancement est effectué par le sheduler et par les déclencheurs de proximité pour accélérer la détection des users présent pour l'alarme:
	Tiens une liste de l'état sur le réseau des users de Tab_User
	Les équipements DOIVENT éxister pour gérer les historiques (via un objet virtuel ('Sys_Routeur' par exemple ou via le plugin du routeur)

	1 - Contôle Geofence (pour les users concernés)

	si lancement par sheduler :
	2 - si pas Connecté parcours la table ARP
	3 - si pas Connecté Ping l'équipement
	4 - si pas Connecté recherche dans le routeur le premier équipement connecté COMPORTANT le nom du user et le marque comme présent
	3 - si pas Connecté teste le BLEA

	Lorsque'un équipement est connecté, met à jour l'IP et le MAC dans Tab_User si le MAC correspond.
	Si "class" et "config" renseigné dans Tab_User, met à jour l'équipement du plugin avec l'IP trouvée

Si le lancement n'est pas effectué par 'schedule', ne teste QUE les users et sort au premier trouvé pour accélérer la désactivation de l'alarme.

Gère le lancement de l'alarme
Met à jour le tableau HTML des users.
************************************************************************************************************************/
global $debug;

// Infos, Commandes et Equipements :
	// $equipTabReseau, $infPorteEntree
	// $equipPresence, $equipAlarme, $equipNuki

// N° des scénarios :
	$scen_LancementAlarme = 57;		// N° du scénario du digicode déclenchant l'alarme

// Variables :
	$timingRelanceJC = 90;			// Durée avant relance service/tracking de JC si pas de nouvelle
	$tempoBLEA = 2*60;				// Durée max en sec	 depuis dernier signal BLEA (multiple du cron de base)
	$tempoNOK = 1*60;				// Tempo avant de passer les user réseau en NOK (multiple du cron de base) /////////////////////////////////////////////////

	$pluginRouteur = 'asuswrt';		// Nom du plugin du routeur actif (Vide si inutilisé (mais il faut obligatoirement les équipements user dans '$equipVirtuel)' )
	$equipVirtuel = 'Sys_Routeur';			// Nom de l'objet auquel sont affectés les équipements (obligatoire si pas de plugin routeur)

// Paramètres :
	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
	$fileExportJS = getRootPath() . "$pathRef/util/tab_Reseau.js";
	$fileExportHTML = getRootPath() . "$pathRef/util/tab_Reseau.html";

	$tabUser = mg::getTabSql('_tabUsers');
	$tabUserTmp = mg::getVar('tabUsersTmp'); // Table des valeurs volatiles utilisé par l'html des users présents
	$timingAlarmeEntree = mg::getParam('Alarme', 'timingEntree');	// Temps maximum (en mn depuis le dernier mouvement de la porte d"entrée pour autoriser le lancement de l"alarme si AutoPrésence.

	$logTimeLine = mg::getParam('Log', 'timeLine');
	$logTest = mg::getParam('Log', 'test');

	$timingAlarmeLastMvmt = mg::getParam('Alarme', 'timingLastMvmt'); // Temps minimum (en mn depuis LastMvmtAll pour autoriser le lancement de l'alarme si AutoPrésence.

/***********************************************************************************************************************/
/***********************************************************************************************************************/
/***********************************************************************************************************************/
$declencheurShedule = mg::declencheur('schedule');
$nbTentatives = 0;
$nbPresences = 0;

// Gestion MàJ du widget (action à "JAMAIS REPETER")
if (mg::declencheur('Maj_Aff')&& mg::getCmd($equipTabReseau, 'Maj_Aff')) {
	mg::messageT('', "! MàJ de l'affichage");
//	mg::debug();
	$HTML = mg::getCmd($equipTabReseau, 'TabReseau_Aff');
	mg::setInf($equipTabReseau, 'TabReseau_Aff', $HTML.' ');
	mg::setInf($equipTabReseau, 'Maj_Aff', 0);
	return;
}
mg::setInf($equipTabReseau, 'Maj_Aff', 0);


// --------------------------------------------------------------------------------------------------------------------
// Parcours de la table des Users
foreach ($tabUser as $user => $detailsUser) {
	$codeUser = trim(str_replace('Tel-', '', $user), '_');

	if ($tabUser[$user]['visible'] == 0) { continue; }
	if (!isset($tabUserTmp[$user]['lastNOK'])) { $tabUserTmp[$user]['lastNOK'] = 0; }
	if (!isset($tabUserTmp[$user]['OK'])) { $tabUserTmp[$user]['OK'] = 0; }

	// Lecture param TabUser
	$IP = isset($tabUser[$user]['IP']) ? trim($tabUser[$user]['IP']) : '';
	$MAC = strtolower($tabUser[$user]['MAC']);
	$class = isset($tabUser[$user]['class']) ? trim($tabUser[$user]['class']) : '';
	$config = isset($tabUser[$user]['config']) ? trim($tabUser[$user]['config']) : '';
	$JC = isset($tabUser[$user]['JC']) ? intVal($tabUser[$user]['JC']) : '0';
	$nomBLEA = isset($tabUser[$user]['BLEA']) ? trim($tabUser[$user]['BLEA']) : '';
	$geofence = isset($tabUser[$user]['geo']) ? floatval($tabUser[$user]['geo']) : 9999; // Distance min pour être considéré comme présent
	$type = isset($tabUser[$user]['type']) ? trim($tabUser[$user]['type']) : ''; // Type 'user' ou ''

	if ($type == 'user') $userPresent = mg::getCmd($equipPresence, $codeUser, $collectDate, $lastValueDate);
	else $lastValueDate = max($tabUserTmp[$user]['OK'], $tabUserTmp[$user]['lastNOK']);

	$equipUser = "[$equipVirtuel][$user]";
	$cmd_id = trim(mg::toID($equipUser, 'Présence'), '#');
	$OK = '';

	// ******** On saute si pas 'schedule' et pas user ********
	if (!$declencheurShedule && !mg::declencheur('user') && $type != 'user') continue;

	// Scan du réseau sinon
	elseif(!$pluginRouteur) ScanReseau($interfaceReseau, $scanReseau);
	// ------------------------------------------------------------------------------------------------------------------
	// ------------------------------------------------------------------------------------------------------------------

	// Test de distance Home
	$distUser = mg::getVar("dist_$user", 0);
	if (!$OK && $geofence > 0) {
		mg::getCmd("#[Sys_Comm][$user][Position]#", '', $collectDate, $valueDate);
		if ($distUser <= $geofence && $distUser >= 0) $OK .= " Position_JC $distUser Km";
		else goto finUser;
	}

	// TEST JC
	if (!$OK && $JC == 1) {

		// Test de présence JC
		if (!$OK && mg::getCmd("#[Sys_Comm][$user][Ensues]#", '', $collectDate, $valueDate) == 1) $OK .= " ENSUES_JC";
		else goto finUser;

		// Test WIFI home JC
		if (!$OK && strpos(mg::getCmd("#[Sys_Comm][$user][Réseau wifi (SSID)]#", '', $collectDate, $valueDate), 'Livebox-MG') !== false) $OK .= " WIFI_JC";
		else goto finUser;
	}

	// Test Bluetooth BLEA
/*	if (!$OK && $nomBLEA) && getBLEA("#[Sys_Présence][$nomBLEA][Rssi]#", $tempoBLEA, $collectDate, $valueDate)) $OK .= ' - BLEA ';
	else $OK = ''; */

	// Test via routeur
	if (!$OK && $pluginRouteur) {
		$eqLogics = eqLogic::all();
		foreach($eqLogics as $eqLogic) {
			if(!$eqLogic->getIsEnable()) continue;
			$name = $eqLogic->getName();
			$typeName = $eqLogic->getEqType_name();
			if ($typeName == $pluginRouteur && strpos($name, $user) !== false) {
				$equipUser = $eqLogic->getHumanName();
				if (mg::getCmd($equipUser, 'Présence', $collectDate, $valueDate) == 1 && mg::getCmd($equipUser, 'Access Point') != 'none') {
					$IP = mg::getCmd($equipUser, 'Adresse IP');
					mg::setValSql('_tabUsers', $user, '', 'IP', $IP);
					$MAC = strtolower($eqLogic->getLogicalId());
					mg::setValSql('_tabUsers', $user, '', 'MAC', $MAC);
					$OK .= " - ROUTEUR";
					break;
				}
			}
		}
	}

	// Test ARP-SCAN de l'IP/MAC
	if (!$pluginRouteur && $MAC && !$OK) {
		if (getUser($user, $IP, $MAC, $interfaceReseau, $scanReseau)) {
			$valueDate = time();
			$OK .= ' - ARP-SCAN ';
		}
	}

	// Test Ping direct de l'adresse IP si lancement shedule ET PAS DE PLUGIN ROUTEUR //////////////////////////////
	if (!$pluginRouteur && $declencheurShedule && $IP && !$OK) {
		if (mg::getPingIP($IP, $user)) {
			$valueDate = time();
			$OK .= " - PING";
		}
	}

	// ******************************** Mise à jour config class plugin si renseignée *********************************
/*		if ($class != '' && $config != '' && $IP) {
		mg::ConfigEquiLogic($class, $user, $config, $IP);
	}*/ // ??????????????????????????????????????????

finUser:
 //************************************* CALCUL DE LA POSITION GLOBALE DU USER ****************************************
	if (/*$OK &&*/ $valueDate > $lastValueDate) $lastValueDate = $valueDate; 
	else goto fin;//continue;
	
	// RECALCUL DU USER OK D'AFFICHAGE
	if ($OK) {
		$tabUserTmp[$user]['OK'] = $lastValueDate;
		$tabUserTmp[$user]['lastNOK'] = 0;
	} else {
		if ($tabUserTmp[$user]['lastNOK'] == 0) {
			$tabUserTmp[$user]['lastNOK'] = $lastValueDate;
		} elseif ((time() - $tabUserTmp[$user]['lastNOK']) > $tempoNOK) {
			$tabUserTmp[$user]['OK'] = -$lastValueDate;

		}
	}

	//************************************************* BILAN USER ****************************************************
	// MàJ user et Compteur de présence user
	if ($type == 'user') {
		if ($OK) {
			$nbPresences++;
			mg::setInf($equipPresence, $codeUser, 1);
		} else mg::setInf($equipPresence, $codeUser, 0);


		$equipJC = "#[Sys_Comm][$user]#";
		// Départ USER
		if ($userPresent && !$OK) {
			// Relance JC
			if ($JC) {
				mg::setCmd($equipJC, 'Modifier Préférences Appli', 'ON', 'Service JC'); 
				sleep(2);
				mg::setCmd($equipJC, 'Modifier Préférences Appli', 'ON', 'tracking');
			}
			mg::Message($logTimeLine, "Présence - Départ de $codeUser (Décl : " . mg::declencheur() . " - '$distUser' Km Km).");
		}

		// Arrivée USER
		if (!$userPresent && $OK ) {
			mg::Message($logTimeLine, "Présence - Arrivée de $codeUser (OK : '$OK' - '$distUser' Km).");
//			mg::setInf($equipPresence, $codeUser, 1);
		}
	}

	fin:
	// ================================================================================================================
	mg::messageT('',  "!" . mg::declencheur() . " " . ($OK ? mg::_debCo_ . "*** $user ($tmp) PRESENT ***" : mg::_debCr_ . "*** $user ABSENT ***") . " Depuis le " . date('d\/m \à H\hi\m\n', $lastValueDate) . mg::_finC_ . " - ($IP / $MAC)");
	// ================================================================================================================

	$tabUserTmp[$user]['equipStat'] = $cmd_id;

} // Fin boucle user

// Gestion serrure NUKI si changement de $nbPresences
if ($nbPresences != mg::getCmd($equipPresence, 'nbUser')) {
	if ($nbPresences > 0 && mg::getCmd ($equipNuki, 'Nom etat') == 'Verrouillée') mg::setCmd ($equipNuki, 'Déverrouiller');
	if ($nbPresences == 0 && mg::getCmd ($equipNuki, 'Nom etat') == 'Déverrouillée') mg::setCmd ($equipNuki, 'Verrouiller');
	mg::setInf($equipPresence, 'nbUser', $nbPresences);
}

mg::messageT('', "! Il y a $nbPresences user(s) sur le site.");

// ********************************************************************************************************************
// *********************************************** GESTION DE L'ALARME ************************************************
// ********************************************************************************************************************
$alarme = mg::getVar('Alarme', 0);
// Arrêt de l'alarme si présence OK et si elle est en route
if ($nbPresences > 0 && $alarme == 2) {
		mg::Message($logTimeLine, "Alarme - Présence détectée. Arrêt de l'Alarme.");
		mg::setCmd($equipAlarme, 'Désactiver');
}

// Mise en route de l'alarme si personne et pas déja active
elseif ($nbPresences == 0 && $alarme != 2) {
	$dureePorte = scenarioExpression::lastChangeStateDuration($infPorteEntree, 1)/60;
	if ($dureePorte >= $timingAlarmeEntree) {
		mg::Message($logTimeLine, "Lancement de l'alarme annulée (pas de porte d'entrée ouverte depuis plus de " . round($dureePorte, 0) . "minutes.");
	}
	elseif (mg::getCmd($infPorteEntree) == 0) {
		mg::Message($logTimeLine, "La porte d'entrée est ouverte. Armement de l'alarme impossible !");
	}
	// Lancement de l'alarme
	else {
mg::Message($logTimeLine, "Alarme - Aucune présence détectée. Lancement de l'alarme.");
	mg::setCmd($equipAlarme, 'Activation Jour');
	}
}

// ********************************************************************************************************************
//************************************** Fabrication du tableau des users *********************************************
// ********************************************************************************************************************
ksort($tabUser, SORT_STRING);
mg::setVar('tabUsersTmp', $tabUserTmp);
//mg::setVar('Présence', $nbPresences);

$HTML = MakeTabReseau($tabUser, $tabUserTmp, $script, $pathRef);
$HTML .= styleTab();

//file_put_contents($fileExportHTML, $HTML); /* Pour Debug */
file_put_contents($fileExportJS, $script);
mg::setInf($equipTabReseau, 'TabReseau_Aff', $HTML);

// ********************************************************************************************************************
// ********************************************************************************************************************
// ********************************************************************************************************************

// ----------------------------------------------------------------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------
// BLEA
function getBLEA($cmd, $tempoBLEA, &$valueDate) {
	if (mg::existCmd($cmd)) {
		$BLEA_RSSI = mg::getCmd($cmd, '', $collectDate, $valueDate);
		$BLEA_Last = scenarioExpression::StateDuration(mg::toID($cmd));
		if ($BLEA_RSSI && $BLEA_RSSI > -190 && $BLEA_Last >= $tempoBLEA) {
			return true;
		}
	}
}

// ----------------------------------------------------------------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------
// Ping direct d'un IP
function PingIP($user, $IP, $nbTentatives=2, $delay=200) {
	$scanIP = shell_exec("sudo ping -c $nbTentatives -t $delay $IP");
	if (strpos($scanIP, '0 received') === false) {
		mg::message('', "PING sur $user - $IP => OK ***");
		return true;
	} else {
		mg::message('', "********** Tentative ping sur $user - $IP => INTROUVABLE ***");
	}
}

// ----------------------------------------------------------------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------
// Test ARP-SCAN du MAC
function ScanReseau(&$interfaceReseau, &$scanReseau, $retry=2, $delay=500) {
	// On ne lance le sondage du nom de réseau et la MàJ des IP que la première fois
	if (!$interfaceReseau) {
		$interfaceReseau = getInterfaceReseau();
		$scanReseau = $scanReseau . shell_exec("sudo arp-scan -I $interfaceReseau --localnet -g -R --retry=$retry -t $delay");
		$scanReseau = explode(PHP_EOL, $scanReseau);
		//mg::message('', print_r($scanReseau, true));
	}
}

// ----------------------------------------------------------------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------
// Lit les IP et MAC pour présence et mise à jour
function getUser(&$user, &$IP, &$MAC, $interfaceReseau, $scanReseau) {
	//mg::message('', "*** $user - IP : $IP - MAC : $MAC ***");
	for($i=1; $i<=count($scanReseau)-1; $i++) {
		$MAC_ = '';
		$IP_ = '';

		// IP
		$regex = "\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}";
		preg_match("/$regex/ui", $scanReseau[$i], $found);
		if (@iconv_strlen($found[0]) > 1) { $IP_ = $found[0]; }
		// MAC
		$regex = "([0-9a-f]{2}(?::[0-9a-f]{2}){5})";
		preg_match("/$regex/ui", $scanReseau[$i], $found);
		if (@iconv_strlen($found[0]) > 1) { $MAC_ = strtolower($found[0]); }

	// MàJ IP et MAC
		if (strtolower($MAC) == $MAC_) {
			//mg::message('', "===============> getUser ==> $i - IP : $IP / $IP_ - MAC : $MAC / $MAC_ ***");
			$IP = $IP_;
			$tabUser[$user]['IP'] = $IP;
//			$tabUser[$user]['MAC'] = strtolower($MAC);
			return true;
		}
	}
}

// ----------------------------------------------------------------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------
// Récupère l'interface réseau de l'adresse IP de Jeedom
function getInterfaceReseau() {
	$IP = mg::getConfigJeedom('core','internalAddr'); // mg::getTag('#IP#');
	preg_match ("#^(([\d]{1,4}.){3}..).{1,4}$#sU", $IP, $match);
	$masqueIP = $match[1];

	$requete = shell_exec("ip -4 -o addr show | grep '$masqueIP'");
	preg_match ("#\d+:\s+([a-z]+[0-9]+)\s.+#sU", $requete, $matche);
	$interfaceReseau = $matche[1];
	mg::message('', "MasqueIP : $masqueIP - InterfaceReseau : $interfaceReseau");
	return $interfaceReseau;
}


// **********************************************************************************************************************
// ******************************************************** MAKE TABLEAU ************************************************
// **********************************************************************************************************************

function MakeTabReseau($tabUser, $tabUserTmp, &$script, $pathRef) {
	$date = date('H\hi\m\n', time());

$HTML = "
<div>
		<table>
			<colgroup>
				<col style=width:100px>
				<col style=width:40px>
				<col style=width:140px>
				<col style=width:100px>
				<col style=width:140px>
				<col style=width:55px>
			</colgroup>
			<tr>
				<th class=titre colspan=6>Liste des Users réseau ( $date )</th>
			</tr>
				<tr>
				<td class=t-0-1>User</td>
				<td class=t-0-2>Etat</td>
				<td class=t-0-3>Depuis</td>
				<td class=t-0-3>IP</td>
				<td class=t-0-3>MAC</td>
				<td class=t-0-3>Histo</td>
			</tr>
";
	// Calcul des dates du graph à afficher
	$startDate = date('Y-m-d',strtotime('-1 month',time()));
	$endDate = date('Y-m-d', time());

	// Boucle des Users
	$lgn = 0; $Size = '25px';
	foreach ($tabUser as $user => $detailsUser) {
		if ($tabUser[$user]['visible'] == 0) { continue; }
//		if (@isset($tabUser[$user]['visible']) && $tabUser[$user]['visible'] == 0) { continue; }
		$IP = @isset($tabUser[$user]['IP']) ? trim($tabUser[$user]['IP']) : '';
		$MAC = @isset($tabUser[$user]['MAC']) ? trim($tabUser[$user]['MAC']) : '';
		$port = @isset($tabUser[$user]['port']) ? trim($tabUser[$user]['port']) : '';

		$equipStat = @isset($tabUserTmp[$user]['equipStat']) ? trim($tabUserTmp[$user]['equipStat']) : '';

		if($port) { $IP2 = $IP . $port; } else { $IP2 = $IP; }
		$user2 = str_replace(' ', '-', $user);

		date_default_timezone_set('UTC');
		$OK = isset($tabUserTmp[$user]['OK']) ? $tabUserTmp[$user]['OK'] : 0;
		$depuis = time() - abs($OK);
		$nbJours = (date('j', abs($depuis))-1);
		$DepuisTxt = "Depuis $nbJours" . 'j ' . date('H\h\ i\m\n', abs($depuis));

		if ($lgn == 0) { $lgn = 1; } else { $lgn = 0; }

		if ($OK > 0) {
				$etat = "<img src=$pathRef/img/img_Binaire/boutons/Rond_ON.png height=$Size;width=$Size alt=PRESENT>";
		} else {
				$etat = "<img src=$pathRef/img/img_Binaire/boutons/Rond_OFF.png height=$Size;width=$Size alt=ABSENT>";
		}

		$btHisto = ($equipStat ? "<button class='boutonHisto Histo$user2'>Histo</button>" : '');

		$HTML .= "
			<tr>
				<td class=c-$lgn-0>$user</td>
				<td class=c-$lgn-1>$etat</td>
				<td class=c-$lgn-2>$DepuisTxt</td>
				<td class=c-$lgn-2><a href='http://$IP2' style='color:red!important;' target='_blank'>$IP</a></td>
				<td class=c-$lgn-2 style='color:darkred!important'>$MAC</td>
				<td class=c-$lgn-2>$btHisto</td>
			</tr>
		";

	$script .=
	"$('.Histo$user2').on('click',function(){ graph('$user', $equipStat); });
	";

	}

$HTML .= "	</table>
</div>";

	return $HTML;
}

// **********************************************************************************************************************
// *********************************************************** STYLE ****************************************************
// **********************************************************************************************************************

function styleTab() {

$STYLE = "
<style type=text/css>

	a{ color: #FF0000; }

	.titre{
		text-align: center;
		font-size:18px !important;
		line-height: 1.5em;
		background-color:#680100;
		color:#ffffff;
	}

	.boutonHisto {
	  user-appearance: none;
	  border: none;
	  font-weight: bold;
	  font-size: 1.2rem;
	  color: white;
	  background: darkblue;
	}

	.t-0-1{line-height: 1.5em; text-align:center; font-size:16px !important; font-weight:bold; background-color:#f56b00; color:#ffffff;}
	.t-0-2{line-height: 1.5em; text-align:center; font-size:16px !important; font-weight:bold; background-color:#f56b00; color:#ffffff;}
	.t-0-3{line-height: 1.5em; text-align:center; font-size:16px !important; font-weight:bold; background-color:#f56b00; color:#ffffff;}

	.c-0-0{text-align:left; font-size:16px !important; font-weight:bold; background-color:#ddd; color:#1c1e22;}
	.c-0-1{text-align:center; font-size:16px !important; font-weight:bold; background-color:#ddd; color:#1c1e22;}
	.c-0-2{text-align:center; font-size:16px !important; font-weight:bold; background-color:#ddd; color:#1c1e22;}

	.c-1-0{text-align:left; font-size:16px !important; font-weight:bold; background-color:#ddddddc9; color:#1c1e22;}
	.c-1-1{text-align:center; font-size:16px !important; font-weight:bold; background-color:#ddddddc9; color:#1c1e22;}
	.c-1-2{text-align:center; font-size:16px !important; font-weight:bold; background-color:#ddddddc9; color:#1c1e22;}
</style>
";
	return $STYLE;
}

