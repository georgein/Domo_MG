<?php

/**********************************************************************************************************************
Alerte_Froid_EDF - 102

	Lance une alerte lors d'une inactivité du capteur (Coupure EDF) OU d'une absence de charge (Panne, Arret).
	Lance une alerte si la température du frigo/congélateur dépasse la limite $TempMax.
	Si 'sous alarme', un destinataire supplémentaire est prévu pour les messages d'alerte.
	ATTENTION :
				Pour la surveillance EDF, l'équipement DOIT ETRE paramétré pour envoyer périodiquement un rapport (indispensable pour détecter l'inactivité), mettre comme périodicité une valeur <= $seuilSansSignal.
				Les variables de paramètrages ci dessous sont à renseigner pour chaque équipement à surveiller dans le tableau d'appel plus bas.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$infTempCongeloSS, $infTempFrigoSalon, $infTempCongeloSalon
//	$infPuissanceCongelo, $infTensionCompteur

// N° des scénarios :

//Variables :
	$alarme = mg::getVar('Alarme');

// Paramètres :
	$destinataires = mg::getParam('EDF', 'destinataires');
	$destinatairesSousAlarme = mg::getParam('EDF', 'destinatairesSousAlarme');	// Destinataires supplémentaires
	$periodicite =	mg::getParam('EDF', 'periodiciteAlerte'); 		// ; Intervalle en mn entre les alertes
	mg::getCmd($infTensionCompteur, '',  $collectDate, $lastValueDate);
	$tempoAlerteEDF = 240; // Temps max en seconde de rafraichissement tension du compteur EDF avant alerte

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
mg::setCron('', "*/$periodicite * * * *");

// Si en alarme (donc personne sur le site) on ajoute CB à la liste des destinataires
if ($alarme == 2) { $destinataires .= $destinatairesSousAlarme; }

alerteEDF($lastValueDate, $tempoAlerteEDF, $destinataires, $periodicite);

// Contrôle température des frigos et congélo
mg::setInf($infAffAlerte, 'AlerteFroid', '');
SuiviTempFrigo($infTempFrigoSalon	, '10.0', $destinataires, $infAffAlerte, $periodicite);
SuiviTempFrigo($infTempCongeloSalon	,'-12', $destinataires, $infAffAlerte, $periodicite);
SuiviTempFrigo($infTempCongeloSS	,'-14', $destinataires, $infAffAlerte, $periodicite);

/**********************************************************************************************************************
											Contrôle température frigo et congélo
**********************************************************************************************************************/
function SuiviTempFrigo($infCmd, $tempMax, $destinataires, $infAffAlerte, $periodicite) {
	// Effacement de l'affichage des températures si pas en alerte)
	$nom = trim(str_replace('_', '', mg::ExtractPartCmd($infCmd, 2)));
	mg::messageT('', "Contrôle de la température de $nom");
	$temp = round(mg::getCmd($infCmd), 1);
	$tempMoyen = mg::getExp("average($infCmd, 30 min)");
	// ALERTE FROID
	if ($temp > $tempMax && $tempMoyen > $tempMax) {
		mg::MessageT('',"! ********ALERTE FROID : $nom ==> Temp : $temp/$tempMoyen - TempMax : $tempMax - NomAlerte : $nom  **********");
		$message = "ALERTE : $nom, ($temp ° au lieu de $tempMax °)";
		mg::Alerte($nom, $periodicite, 1440, $destinataires, $message);
		mg::setCron('', "*/$periodicite * * * *");
		mg::setInf($infAffAlerte, 'AlerteFroid', $message);
		return;
		
	// FIN D'ALERTE
	} else {
		if (mg::getVar("_Alerte$nom")) {
			mg::Alerte($nom, -1);
			mg::setInf($infAffAlerte, 'AlerteFroid', '');
		} else if (strpos(mg::getCmd($infAffAlerte, 'AlerteFroid'), "ALERTE") === false) { 
			// Affiche températures
			mg::setInf($infAffAlerte, 'AlerteFroid', trim(trim(mg::getCmd($infAffAlerte, 'AlerteFroid'), '|')) . " | " . round($temp) . "°");
		}
	}
}

// ********************************************************************************************************************
// **************************************************** ALERTE EDF ****************************************************
// ********************************************************************************************************************
function alerteEDF($lastValueDate, $tempoAlerteEDF, $destinataires, $periodicite) {
	$nomAlerte = 'Coupure_EDF';
	$AlerteEDF = (time() - $lastValueDate) > $tempoAlerteEDF ? 1 : 0;

$daemon = 'jMQTT';
$daemonInfo = $daemon::deamon_info();
$etatJMQTT = $daemonInfo['state'];
	mg::messageT('', "CONTROLE EDF - AlerteEDF : ($AlerteEDF) ".(time() - $lastValueDate)." sec / $tempoAlerteEDF - etatJMQTT : $etatJMQTT");


	// SI COUPURE EDF, MISE EN SECURITE PC-MG
	if ($AlerteEDF && $etatJMQTT == 'ok' && mg::getVar('_AlerteEDF', 0) == 0) {
		$message =	"ALERTE : COUPURE EDF EN COURS";
		mg::message($destinataires, $message);
		mg::Alerte($nomAlerte, $periodicite, 1440, $destinataires, $message);
		mg::setVar('_AlerteEDF', 1);
		mg::setCron('', "*/$periodicite * * * *");
		return;

	// EDF OK
	} elseif (!$AlerteEDF && mg::getVar('_AlerteEDF', 0) == 1) {
		mg::Alerte($nomAlerte, -1);
		mg::message($destinataires, "FIN DE LA COUPURE EDF.");
		mg::unsetVar('_AlerteEDF');
		mg::WakeOnLan('PC-MG');
	}
	
	mg::setCron('', "*/15 * * * *");
}

?>
