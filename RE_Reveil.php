<?php
/**********************************************************************************************************************
Réveil Chambre - 48
Scénario permettant le réveil journalier avec montée progressive du volet de la chambre et mise en route de la radio.
Une annonce constituée de la météo locale, des prévisions de Météo France et des Une du Monde sont vocalisées.

Si le réveil démarre avec nuitSalon != 2 (levé prématuré) on ouvre la chambre que à la phase d'extinction de la radio.
**********************************************************************************************************************/
// Infos, Commandes et Equipements :
// $infReveil, $infMusique, $equipMvmtEtage
// $equipVoletChambre, $equipVoletRdCSdB, $equipVoletEtage

// N° des scénarios :

//Variables :
	$urlMonde = "http://www.lemonde.fr/rss/une.xml";											//	Le Monde à la UNE
	//$urlMonde = "http://www.lemonde.fr/m-actu/rss_full.xml";									// Le Monde en BREF
//	$radioSDB = 'http://direct.franceinfo.fr/live/franceinfo-midfi.mp3';
//	$radioSDB = 'http://icecast.radiofrance.fr/fip-midfi.mp3';

	$heureReveil = mg::getVar('heureReveil'); 
	$nuitSalon = mg::getVar('NuitSalon');
	$alarme = mg::getVar('Alarme');
	$lastMvmt = round(mg::lastMvmt($infNbMvmtSalon, $nbMvmt)/60);

// Paramètres :
	$equipSonos = mg::getParam('Media', 'equipSonos');
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$sonosVolumeDefaut = mg::getParam('Media', 'sonosVolumeDefaut');
	$reveilDestinataires = mg::getParam('Reveil', 'destinataires');	// Destinataires du message vocal de fin
	$reveilDureeRadio = mg::getParam('Reveil', 'dureeRadio');			// Durée en mn avant l'arrêt de la radio
	$reveilVolumeRadio = mg::getParam('Reveil', 'volumeRadio');		// Volume de la radio
	$reveilStationRadio = mg::getParam('Reveil', 'stationRadio');		// Station de radio à lancer
	 
	$heureRelance = strtotime('08:30');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
deb:
$reveilOnLine = mg::getVar('_ReveilOnLine', 0);

// Si réveil à Off ou sous Alarme, on sort
if ( $alarme == 1 || !mg::getCmd($infReveil)) { return; }

// Si réveil OnLine on démarre Sonos et on ouvre les volets de la chambre
if (!$reveilOnLine) {
	//=================================================================================================================
	mg::messageT('', "! 0 PREMIERE PARTIE");
	//=================================================================================================================
	mg::Message($logTimeLine, "Reveil - Lancement.");
	// ------------------------- Cron pour Relancement reprise suite plantage --------------------
	mg::setCron('', time() + 60);

	//=================================================================================================================
	mg::messageT('', ". MISE EN ROUTE RADIO");
	//=================================================================================================================
	// Met la radio si pas de présence d'invité à l'étage
	if (mg::getCmd($infMusique) == 1 && mg::getCmd($equipMvmtEtage, 'Présence') <= 0) {
		mg::setCmd($equipSonos, 'Volume', $reveilVolumeRadio);
		$infVolSonos = mg::mkCmd($equipSonos, 'Volume Status');
		self::wait ("$infVolSonos == $reveilVolumeRadio", 5);
		mg::setCmd($equipSonos, 'Jouer une radio', '.', $reveilStationRadio);
	}

	//=================================================================================================================
	mg::messageT('', ". ON ENTROUVRE LE VOLET CHAMBRE");
	//=================================================================================================================
	if (mg::getCmd($equipVoletChambre, 'Etat') < 1) {
		if 	(time() >= $heureRelance || ($nuitSalon == 2 && $lastMvmt > 60)) {
			mg::setCmd($equipVoletChambre, 'position', 10);
			mg::setCmd($equipVoletRdcSdB, 'up');
		}
//	} else {
//		mg::setVar('heureReveil', $heureRelance);
//		$heureReveil = $heureRelance;
	}
	
	mg::setVar('_ReveilOnLine', 2);
	mg::setCron('', $heureReveil + 15 * 60);

} elseif ($reveilOnLine == 2) {
	//=================================================================================================================
	mg::messageT('', "! 2 OUVERTURE TOTALE CHAMBRE");
	//=================================================================================================================
	if (mg::getCmd($equipVoletChambre, 'Etat') > 1) {
		mg::setCmd($equipVoletChambre, 'up');
	}
		
	mg::setVar('_ReveilOnLine', 3);
	mg::setCron('', ($heureReveil + $reveilDureeRadio * 60));

} elseif ($reveilOnLine == 3) {
	//=================================================================================================================
	mg::messageT('', ". 3 ANNONCE VOCALE");
	//=================================================================================================================
		mg::Message($logTimeLine, "Reveil - Annonce vocale.");

	//=================================================================================================================
	mg::messageT('', ". METEO LOCALE");
	//=================================================================================================================
	$message = MsgMeteoLocale();

	$message .= "(...)\n(...) " . mg::getVar('_Notif_ICO');

	//=================================================================================================================
	mg::messageT('', ". MESSAGES DES RDV");
	//=================================================================================================================
//	$message .= "(...)\n(...) " . MessageRdV(mg::getParam('Global', 'ICS_VACANCES'), 'Vacances scolaires');
	$message .= "(...)\n(...) " . MessageRdV(mg::getParam('Global', 'ICS_FERIES'), 'fériés');
	$message .= "(...)\n(...) " . MessageRdV(mg::getParam('Global', 'ICS_NR'), 'N R');
	$message .= "(...)\n(...) " . MessageRdV(mg::getParam('Global', 'ICS_MG'), 'M G');

	//=================================================================================================================
	mg::messageT('', ". MESSAGES Evolution Poids");
	//=================================================================================================================
	$message .= "(...)\n(...) " . MessagePoids('MG', 'M G');
	$message .= "(...)\n(...) " . MessagePoids('NR', 'N R');

	//=================================================================================================================
	mg::messageT('', ". TITRE DU MONDE");
	//=================================================================================================================
	$message .= "(...)\n(...) " . MondeALaUne($urlMonde, $reveilDestinataires); 

	mg::Message("$reveilDestinataires", "@$message");

	//=================================================================================================================
	mg::messageT('', ". REMISE EN ROUTE RADIO");
	//=================================================================================================================
	shell_exec("sudo rm -f /var/www/html/log/scenarioLog/scenario48.log"); // Pour éviter les "error" de monitoring
	
	// ***** SI volets chambre baissés on relance le réveil et on coupe la radio *****
	if (mg::getCmd($equipVoletChambre, 'Etat') < 1 && $heureRelance > $heureReveil) {
		mg::Message($logTimeLine, "Reveil - RELANCE DU REVEIL POUR ".date('H\hi\m\n', $heureRelance).".");
		mg::unsetVar('_ReveilOnLine');

		$heureReveil = $heureRelance;
		mg::setVar('heureReveil', $heureReveil);
		mg::setCron('', $heureReveil);
		mg::setCmd($equipSonos, 'Stop');
		return;
	}

	// SINON on REMet la radio 
	else {
		mg::setCmd($equipSonos, 'Volume', $reveilVolumeRadio);
		$infVolSonos = mg::mkCmd($equipSonos, 'Volume Status');
		self::wait ("$infVolSonos == $reveilVolumeRadio", 5);
		mg::setCmd($equipSonos, 'Jouer une radio', '.', $reveilStationRadio);
		mg::setCron('', $heureReveil + 1.5*3600);
		mg::setVar('_ReveilOnLine', 4);
	}
	
} elseif ($reveilOnLine == 4) {
	//=================================================================================================================
	mg::messageT('', ". 4 FIN DE PROCESS");
	//=================================================================================================================
	mg::Message($logTimeLine, "Reveil - Arrêt de Sonos.");
	mg::setCron('', time()-1);
	mg::setCmd($equipSonos, 'Stop');
	mg::unsetVar('_ReveilOnLine');

	mg::setCmd($equipVoletEtage, 'up');
}

/**********************************************************************************************************************
											Fabrique le message de suivi des poids
**********************************************************************************************************************/
function MessagePoids($user, $userLong) {
	$infPoidsUser = trim(mg::toID("[Sys_Présence][Balance $user]", 'Poids'), '#');
	$poids = intVal(mg::getCmd($infPoidsUser));

  	$startDate = date('Y-m-d', time()-6*24*3600) . ' 00:00:00';
  	$endDate = date('Y-m-d', time()) . ' 23:59:59';
	$avgWeek = scenarioExpression::averageBetween(trim($infPoidsUser, '#'), $startDate, $endDate);
  	$deltaWeek = round($avgWeek - $poids, 1);
	$sensWeek = $deltaWeek > 0 ? "en baisse" : "en hausse";
	$deltaWeek = abs($deltaWeek);

  	$startDate = date('Y-m-d', time()-30*24*3600) . ' 00:00:00';
  	$endDate = date('Y-m-d', time()-24*24*3600) . ' 23:59:59';
	$avgMois = intval(scenarioExpression::averageBetween(trim($infPoidsUser, '#'), $startDate, $endDate));
	$deltaMois = round($avgMois - $poids, 1);
	$sensMois = $deltaMois > 0 ? "en baisse" : "en hausse";
	$deltaMois = abs($deltaMois);

  	$return = str_replace('.', ',', "Le dernier poids de $userLong était de $poids kg, $sensWeek de $deltaWeek Kg sur une semaine et $sensMois de $deltaMois kg sur un mois");
	return $return;
}

/**********************************************************************************************************************
													Fabrique le message de RdV
**********************************************************************************************************************/
function MessageRdV($pathICS, $user) {
	$rdvs = mg::getICS($pathICS, strtotime("- 0 day"), strtotime("now"));
	$message = '';
	if (is_array($rdvs) && count($rdvs) > 0) {
		foreach ($rdvs as $i => $rdv) {
			if ( strtotime($rdv['start']) > 0) {
				$message .= "(...)\n(...) " . date('H\ \h\e\u\r\e\ i', strtotime($rdv['start'])) . ' : ' . $rdv['title'];
				$message = str_replace('00 heure 00 : ', '', $message);
			} else {
				$message .= "(...)\n(...)" . $rdv['title'];
			}
		}
	} else {
 	$message = "(...)\n(...) Aucun";
		}
	if ($user != 'fériés' ) {
		return "(...)\n(...) Les rendez vous du jour de $user (...)$message";
	} else {
		if (is_array($rdvs) && count($rdvs) != 0) return "(...)\n(...) Jour de $message, c'est fériés !";
	}
}

/**********************************************************************************************************************
													Fabrique le message Météo locale
**********************************************************************************************************************/
function MsgMeteoLocale() {

	// Date du jour
	$LJour = mg::getTag('#sjour#');
	$NJour = trim(mg::getTag('#jour#'));
	$LMois = mg::getTag('#smois#');
	$Heure = trim(mg::getTag('#heure#'));
	$Minute = trim(mg::getTag('#minute#'));
	$message = "Nous sommes aujourd'hui le $LJour $NJour $LMois et il est $Heure heures $Minute minutes(...)\n(...)";

	$message .= mg::getVar('_MeteoLocale');

	return $message;
}

/**********************************************************************************************************************
													Fabrique le message Monde à la Une
**********************************************************************************************************************/
function MondeALaUne($urlMonde, $reveilDestinataires) {

	$rss = simplexml_load_file($urlMonde);
	$i=1;

	$messageComplet = "Les titres du Monde à la Une : (...)";
	foreach ($rss->channel->item as $item){
		$message = "$item->title : $item->description";
		$message = str_replace('dent', 'dans', $message); // président => president (il conjugue les nom au pluriel en ent ...)
		// Filtre de sujet
		$message = strtolower($message);
		if (strpos($message, 'sport') !== false
			|| strpos($message, 'football') !== false
			|| strpos($message, 'tennis') !== false
			|| strpos($message, 'de france') !== false
			|| strpos($message, 'coupe')
			|| strpos($message, 'tour de france') !== false
			|| strpos($message, 'champion') !== false
			|| strpos($message, 'es bleus') !== false
			|| strpos($message, 'psg') !== false
			|| strpos($message, 'roland-garros') !== false
		)
			{ $message = '';}

		if ($message != '')	{
			$messageComplet .= "\n(...)$i(...) $message";
			$i++;
		}
	}
	$messageComplet .= "\n(...) Fin des titres du Monde, (...) bonne journée.";
	return $messageComplet;
}
?>