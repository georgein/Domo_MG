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
//	$infPuissanceCongelo, $infStatutEDF, $infSecondaire

// N° des scénarios :

//Variables :
	$alarme = mg::getVar('Alarme');

// Paramètres :
	$destinataires = mg::getParam('EDF', 'destinataires');
	$destinatairesSousAlarme = mg::getParam('EDF', 'destinatairesSousAlarme');	// Destinataires supplémentaires
	$periodicite =	mg::getParam('EDF', 'periodiciteAlerte'); 		// ; Intervalle en mn entre les alertes
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$statutEDF = mg::getCmd($infStatutEDF);

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
mg::setCron('', "*/$periodicite * * * *");

// Si en alarme (donc personne sur le site) on ajoute CB à la liste des destinataires
if ($alarme == 1) { $destinataires .= $destinatairesSousAlarme; }

if (mg::declencheur('EDF') || $statutEDF = 2) alerteEDF($infStatutEDF, $destinataires, $infSecondaire, $periodicite);

// Contrôle température des frigos et congélo
mg::setInf($infAffAlerte, 'AlerteFroid', '');
SuiviTempFrigo($infTempFrigoSalon	, '12.0', $destinataires, $infAffAlerte, $periodicite);
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
		mg::setInf($infAffAlerte, 'AlerteFroid', $message);

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

// ***************************************************************************************************************** //
// ***************************************************************************************************************** //
// ***************************************************************************************************************** //
function alerteEDF($infStatutEDF, $destinataires, $infSecondaire, $periodicite) {
	$statutEDF = mg::getCmd($infStatutEDF);
	$nomAlerte = 'Coupure_EDF';
	$message =	"ALERTE : COUPURE EDF EN COURS";
mg::messageT('', "CONTROLE EDF");

	// COUPURE EDF
	if ($statutEDF == 2) {
//		mg::message($destinataires, $message);
		mg::Alerte($nomAlerte, $periodicite, 1440, $destinataires, $message);

	//	mg::eventGhost('Veille', 'PC-MG');
	//	sleep(10);
	//	mg::setCmd($infSecondaire, 'Off');

	// EDF OK
	} elseif (mg::declencheur('EDF') && $statutEDF == 3) {
		mg::setCmd($infSecondaire, 'On');
		sleep(2);
		mg::WakeOnLan('PC-MG');

		mg::message($destinataires, "FIN DE LA COUPURE EDF.");
		mg::Alerte($nomAlerte, -1);
	}

}
?>
