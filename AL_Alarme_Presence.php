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
	// $infNbMvmtSalon, $infTabReseau_Aff
	// $EquipGeoloc,

// N° des scénarios :
	$scen_LancementAlarme = 57;		// N° du scénario du digicode déclenchant l'alarme

// Variables :
	$tempoBLEA = 2*60;				// Durée max en sec	 depuis dernier signal BLEA (multiple du cron de base)
	$tempoNOK = 2*60;				// Tempo avant de passer les user réseau en NOK (multiple du cron de base)

	$pluginRouteur = 'asuswrt';		// Nom du plugin du routeur actif (Vide si inutilisé (mais faut obligatoirement les équipements user dans '$equipVirtuel)' )
	$equipVirtuel = 'Sys_Routeur';			// Nom de l'objet auquel sont affectés les équipements (obligatoire si pas de plugin routeur)

// Paramètres :
	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
	$fileExportJS = getRootPath() . "$pathRef/util/tab_Reseau.js";
	$fileExportHTML = getRootPath() . "$pathRef/util/tab_Reseau.html";
	$alarme = mg::getVar('Alarme');
	$tabUser = mg::getVar('tabUser');
	$tabUserTmp = mg::getVar('_tabUser'); // Table des valeurs volatiles
	$logAlarme = mg::getParam('Log', 'alarme');

	$timingAlarmeLastMvmt = mg::getParam('Alarme', 'timingLastMvmt'); // Temps minimum (en mn depuis LastMvmtAll pour autoriser le lancement de l'alarme si AutoPrésence.

/***********************************************************************************************************************/
/***********************************************************************************************************************/
/***********************************************************************************************************************/
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

// Scan du réseau
/*if (mg::declencheur('schedule') || mg::declencheur('user')) { 
	ScanReseau($interfaceReseau, $scanReseau);
}*/

// --------------------------------------------------------------------------------------------------------------------
// Parcours de la table des Users
foreach ($tabUser as $user => $detailsUser) {
	if ($tabUser[$user]['visible'] == 'false') { continue; }
	if (!isset($tabUserTmp[$user]['lastNOK'])) { $tabUserTmp[$user]['lastNOK'] = 0; }
	if (!isset($tabUserTmp[$user]['OK'])) { $tabUserTmp[$user]['OK'] = 0; }

	// Lecture param TabUser
	$IP = isset($tabUser[$user]['IP']) ? trim($tabUser[$user]['IP']) : '';
	$MAC = strtolower($tabUser[$user]['MAC']);
	$class = isset($tabUser[$user]['class']) ? trim($tabUser[$user]['class']) : '';
	$config = isset($tabUser[$user]['config']) ? trim($tabUser[$user]['config']) : '';
	$nomBLEA = isset($tabUser[$user]['BLEA']) ? trim($tabUser[$user]['BLEA']) : '';
	$geofence = isset($tabUser[$user]['geo']) ? floatval($tabUser[$user]['geo']) : 9999; // Distance min pour être considéré comme présent
	$type = isset($tabUser[$user]['type']) ? trim($tabUser[$user]['type']) : ''; // Type 'user' ou ''

	$equipUser = "[$equipVirtuel][$user]";
	$cmd_id = trim(mg::toID($equipUser, 'Présence'), '#');
	$OK = null;

	// ******** On saute si pas 'schedule' et (pas user ********
	if (!mg::declencheur('schedule') && !mg::declencheur('user') && $type != 'user') continue; 
	// Scan du réseau sinon
	else ScanReseau($interfaceReseau, $scanReseau); 
	// ------------------------------------------------------------------------------------------------------------------
	// ------------------------------------------------------------------------------------------------------------------
	// Test de localisation
	if ($geofence > 0 && !$OK) {
		if (mg::getVar("dist_$user") > 0 && mg::getVar("dist_$user") < $geofence) {
			$OK .= " GEOFENCE";
		}
	}

	// Test ARP-SCAN de l'IP/MAC
	if ($MAC && !$OK) {
		if (getUser($user, $IP, $MAC, $interfaceReseau, $scanReseau)) {
			$OK .= ' - ARP-SCAN ';
		}
	}

	// Test Bluetooth BLEA
	if ($nomBLEA && !$OK) {
		if (getBLEA("#[Sys_Présence][$nomBLEA][Rssi]#", $tempoBLEA)) {
			$OK .= ' - BLEA ';
		} else {
			$OK = '';
		}
	}
	
	// Test via routeur
	if ($pluginRouteur && !$OK) {
		$presence = 0;
		$eqLogics = eqLogic::all();
		foreach($eqLogics as $eqLogic) {
			$name = $eqLogic->getName();
			$typeName = $eqLogic->getEqType_name();
			if ($typeName == $pluginRouteur && strpos($name, $user) !== false) {
				$equipUser = $eqLogic->getHumanName();
				$cmd_id = trim(mg::toID($equipUser, 'Présence'), '#');
				$presence = mg::getCmd($cmd_id);
				if ($presence) {
					$IP = mg::getCmd($equipUser, 'Adresse IP') ? mg::getCmd($equipUser, 'Adresse IP') : $IP;
					$MAC = strtolower($eqLogic->getLogicalId());
					$OK .= " - ROUTEUR";
					continue;
				}
			}
		}
	}
	
	// Test Ping direct de l'adresse IP si lancement shedule
	if (mg::declencheur('schedule') && $IP && !$OK) {
		if (mg::getPingIP($IP, $user)) {
			$OK .= " - PING";
		}
	}

	// ******************************** Mise à jour config class plugin si renseignée *********************************
		if ($class != '' && $config != '' && $IP) {
		mg::ConfigEquiLogic($class, $user, $config, $IP);
	}

 //************************************* CALCUL DE LA POSITION GLOBALE DU USER ****************************************
	// RECALCUL DU USER OK D'AFFICHAGE
	if ($OK) {
		$tabUserTmp[$user]['OK'] = (time() - scenarioExpression::lastChangeStateDuration($cmd_id, 1));
		$tabUserTmp[$user]['lastNOK'] = 0;
	} else {
		if ($tabUserTmp[$user]['lastNOK'] == 0) {
			$tabUserTmp[$user]['lastNOK'] = time();
		} elseif ((time() - $tabUserTmp[$user]['lastNOK']) > $tempoNOK) {
			$tabUserTmp[$user]['OK'] = -(time() - scenarioExpression::lastChangeStateDuration($cmd_id, 0));

		}
	}

	//************************************************* BILAN USER ****************************************************
	// Compteur de présence user
	if ($type == 'user' && $OK) { $nbPresences++; }

	// ================================================================================================================
	mg::message('', "$user " . ($OK ? "PRESENT" : "***ABSENT***)") . " Depuis le " . date('d\/m \à H\hi\m\n', (abs($tabUserTmp[$user]['OK']))) . " sec. ($OK) - ($IP / $MAC)");
	// ================================================================================================================

	$tabUserTmp[$user]['equipStat'] = $cmd_id;
	if ($IP) { $tabUser[$user]['IP'] = $IP; }
	$tabUser[$user]['MAC'] = strtolower($MAC);
} // Fin boucle user

	mg::messageT('', "! Il y a $nbPresences user(s) sur le site.");

// ********************************************************************************************************************
// *********************************************** GESTION DE L'ALARME ************************************************
// ********************************************************************************************************************
// Arrêt de l'alarme si présence OK et si elle est en route
if ($nbPresences && $alarme == 1) {
	mg::Message("$logAlarme/_TimeLine", "Alarme - Présence détectée. Arrêt de l'Alarme.");
	mg::setScenario($scen_LancementAlarme, 'start');
}
// Mise en route de l'alarme si personne et pas déja active et si pas inhibée et si pas de mvmt depuis TimingAlarmeLastMvmt
elseif (!$nbPresences && $alarme == 0) {
mg::Message("$logAlarme/_TimeLine", "Alarme - Aucune présence détectée. Lancement de l'alarme.");
mg::setScenario($scen_LancementAlarme, 'start');
}

// ********************************************************************************************************************
//************************************** Fabrication du tableau des users *********************************************
// ********************************************************************************************************************
ksort($tabUser, SORT_STRING);
mg::setVar('tabUser', $tabUser);
mg::setVar('_tabUser', $tabUserTmp);
mg::setVar('Présence', $nbPresences);

$HTML = MakeTabReseau($tabUser, $tabUserTmp, $script, $pathRef);
$HTML .= styleTab();

//file_put_contents($fileExportHTML, $HTML); /* Pour Debug */
file_put_contents($fileExportJS, $script);
mg::setInf($infTabReseau_Aff, '', $HTML);

// ********************************************************************************************************************
// ********************************************************************************************************************
// ********************************************************************************************************************

// ----------------------------------------------------------------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------
// BLEA
function getBLEA($cmd, $tempoBLEA) {
	if (mg::existCmd($cmd)) {
		$BLEA_RSSI = mg::getCmd($cmd);
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
		if ($tabUser[$user]['visible'] == 'false') { continue; }
		if (@isset($tabUser[$user]['visible']) && $tabUser[$user]['visible'] == 'non') { continue; }
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

