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
//	$infPuissanceEDF, $infPuissanceCongelo, $infPuissanceInfoSauvegarde, $infAffAlerte
// N° des scénarios :

//Variables :
	$alarme = mg::getVar('Alarme');

// Paramètres :
	$destinataires = mg::getParam('EDF', 'destinataires'); 
	$destinatairesSousAlarme = mg::getParam('EDF', 'destinatairesSousAlarme');	// Destinataires supplémentaires

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

//==================================================================================
// Si en alarme (donc personne sur le site) on ajoute CB à la liste des destinataires
if ($alarme == 1) { $destinataires .= $destinatairesSousAlarme; }

//=====================================================================================================================
// Contrôle température des frigos et congélo
// Effacement de l'affichage des températures si pas en alerte)
if (strpos(mg::getCmd($infAffAlerte, 'AlerteFroid'), "ALERTE") === false) { mg::setInf($infAffAlerte, 'AlerteFroid', ''); }
SuiviTempFrigo($infTempFrigoSalon	, '12.0', $destinataires, $infAffAlerte);
SuiviTempFrigo($infTempCongeloSalon	,'-12', $destinataires, $infAffAlerte);
SuiviTempFrigo($infTempCongeloSS	,'-14', $destinataires, $infAffAlerte); 

//=====================================================================================================================
// Contrôle EDF et Charges des équipements
//		($nom, $numAlerte,	$infPuissance,				$seuilConso,	$seuilHS en mn, $seuilSansSignal en mn,	$destinataires)
EDF(	'EDF',		2,		$infPuissanceEDF,				100,			5,				5,					$destinataires, $infAffAlerte);
EDF(	'Informatique',	3,	$infPuissanceInfoSauvegarde,	20,				5,				5,					$destinataires, $infAffAlerte);
EDF(	'Congélo',	1,		$infPuissanceCongelo,			10,				15,				10,					$destinataires, $infAffAlerte);

/**********************************************************************************************************************
											contrôle EDF et de charge (consommation)
**********************************************************************************************************************/
function EDF($nom, $numAlerte, $infPuissance, $seuilConso, $seuilHS, $seuilSansSignal, $destinataires, $infAffAlerte) {
	$periodicite =	mg::getParam('EDF', 'periodiciteAlerte'); 		// ; Intervalle en mn entre les alertes
	// Init des valeurs
	$cmd = cmd::byString($infPuissance);
	$puissance = $cmd->execCmd();
	$collectDate = $cmd->getCollectDate();
	$dureeHS = 0;
	
	// ================================================================================================================
	$dureeSansSignal = round( (time() - strtotime($collectDate)) / 60);
	if ($dureeSansSignal > $seuilSansSignal) {
		$compMessage = "(EDF, Disjoncteur ...)";
	}

	// ================================================================================================================
	// Calcul alerte sur consommation trop faible
	if ($puissance < $seuilConso) {
		$debutAlerte = mg::getVar('_alerteConso', time());
		$dureeHS = round((time() - $debutAlerte) / 60);
		$compMessage = "(Panne, Arrét ...)";
	}

	// ================================================================================================================
	// ALERTE EDF
	if ( $dureeSansSignal > $seuilSansSignal || $dureeHS > $seuilHS ) {
		// ************************************************************************************************************
		mg::MessageT('',". ALERTE $nom ==> DureeSansSignal : $dureeSansSignal / $seuilSansSignal - puissance : $puissance < $seuilConso - DureeHS : $dureeHS / $seuilHS");
		// ************************************************************************************************************
		$message =	"ALERTE : $nom Ensuèse la Redonne HORS SERVICE $compMessage";
		mg::Alerte($nom, $periodicite, 9999, $destinataires, $message);
		mg::setInf($infAffAlerte, 'AlerteEDF', $message);
		
	//=================================================================================================================
	// FIN D'ALERTE
	} else {
		if (mg::getVar("_Alerte$nom")) {
			mg::Alerte($nom, -1);
			mg::setInf($infAffAlerte, 'AlerteEDF', '');
		}
		else if (strpos(mg::getCmd($infAffAlerte, 'AlerteEDF'), "ALERTE") === false) {
		mg::setCron('', '*/10 * * * *');
		}
	}
}

/**********************************************************************************************************************
											Contrôle température frigo et congélo
**********************************************************************************************************************/
function SuiviTempFrigo($infCmd, $tempMax, $destinataires, $infAffAlerte) {
	$periodicite =	mg::getParam('EDF', 'periodiciteAlerte'); 
	$nom = trim(str_replace('_', '', mg::ExtractPartCmd($infCmd, 2)));
	$temp = round(mg::getCmd($infCmd), 1);
	$tempMoyen = mg::getExp("average($infCmd, 30 min)");
	// ALERTE FROID
	if ($temp > $tempMax && $tempMoyen > $tempMax) {
	mg::Message('',"********ALERTE FROID : $nom ==> Temp : $temp/$tempMoyen - TempMax : $tempMax - NomAlerte : $nom  **********");
		$message = "ALERTE : $nom, ($temp ° au lieu de $tempMax °)";
		mg::Alerte($nom, $periodicite, 9999, $destinataires, $message);
		mg::setInf($infAffAlerte, 'AlerteFroid', $message);

	// FIN D'ALERTE
	} else {
			mg::Alerte($nom, -1);
//			mg::setInf($infAffAlerte, 'AlerteFroid', '');
		if (mg::getVar("_Alerte$nom")) {
//			mg::Alerte($nom, -1);
			mg::setInf($infAffAlerte, 'AlerteFroid', '');
		} else if (strpos(mg::getCmd($infAffAlerte, 'AlerteFroid'), "ALERTE") === false) {
			mg::setInf($infAffAlerte, 'AlerteFroid', trim(trim(mg::getCmd($infAffAlerte, 'AlerteFroid'), '|')) . " | " . round($temp) . "°");
			mg::setCron('', '*/10 * * * *');
		}
	}
}
?>
