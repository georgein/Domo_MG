<?php
/**********************************************************************************************************************
Ouvertures Salon - 121
Fait une demande lumineuse pour ouvrir ou fermer les portes selon les températures salon/Extérieure ET SI lumière dans le salon OU nuit extérieure.
Enchaîne au final le scénario ChauffageMode
**********************************************************************************************************************/

// Infos, Commandes et Equipements
//	$infTempExt, $infTempSalon, $infNbPortesSalon
//	$equipLampeCouleur

// N° des scénarios :

//Variables :
	$tabChauffages_ = mg::getVar('_tabChauffages');

	$saison = mg::getVar('Saison');
	$alarme = mg::getVar('Alarme');
	$nuitSalon = mg::getVar('NuitSalon');
	$nuitExt = mg::getVar('NuitExt');
	$heure_Reveil = mg::getVar('_Heure_Reveil');
	$destinataires = mg::getParam('Chauffages','destinatairesPortes');				// Destinataire du message d'annonce
	$timerPortes = 5;
	
// Paramètres :
	$tempSeuilPorte = mg::getParam('Chauffages','tempSeuilPorte');				// Différence de température maximum pour signal lumineux d'ouverture/fermeture de portes
	$intensiteSignalPorte = mg::getParam('Chauffages','intensiteSignalPorte');	// Intensité de la lampe couleur

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
// Pause pour éviter les faux signaux en cas d'ouverture/fermeture ponctuelle
$declencheur = mg::getTag('#trigger#');
if (strpos($declencheur, 'NbPortes') !== false) { 
	sleep(30); 
	mg::setCron('', time() + $timerPortes*60);
}

$demandeFaite = mg::getVar('_DemandeFaite');

// Sortie si NuitExt ou NuitSalon == 2 ou en alarme
if ( $nuitSalon == 2 || $alarme) {
		mg::LampeCouleur($equipLampeCouleur, 0);
		return;
	}

$nbPortesSalon = mg::getCmd($infNbPortesSalon);
$tempSalon = mg::getCmd($infTempSalon);
$tempExt = mg::getCmd($infTempExt);
$difference = $tempSalon - $tempExt;

mg::MessageT('', "Saison : $saison - (TempExtérieure : $tempExt - TempSalon : $tempSalon) => (TempSeuilPorte : $tempSeuilPorte - Différence avec Ext. : $difference");

// Sortie si différence non significative ou trop élevé
if ($difference < $tempSeuilPorte /*|| $difference > 2 * $tempSeuilPorte*/) {
	mg::LampeCouleur($equipLampeCouleur, 0);
	mg::unsetVar('_DemandeFaite');
	return;
}

// Demande d'ouverture de porte
if ( ($saison == 'ETE' && $difference >= $tempSeuilPorte) || ($saison == 'HIVER' && $difference <= $tempSeuilPorte) ) {
	if ($nbPortesSalon == 0) {
	mg::Message('', "---------------------------- DEMANDE OUVERTURE PORTE ------------------------------------");
		mg::LampeCouleur($equipLampeCouleur, $intensiteSignalPorte, mg::VERT);
		if (!$demandeFaite) {
			($saison == 'HIVER') ? $sens = 'montée' : $sens = 'descendue';
			mg::message($destinataires, "@La température moyenne extérieure est $sens à $tempExt °, veuillez ouvrir le salon.");
			mg::setVar('_DemandeFaite', 1);
		}
	} else {
		mg::LampeCouleur($equipLampeCouleur, 0);
		mg::unsetVar('_DemandeFaite');
	}

// Demande de fermeture de porte
} else if ( ($saison == 'ETE' && $difference < $tempSeuilPorte) || ($saison == 'HIVER' && $difference > $tempSeuilPorte) ) {
	if ($nbPortesSalon > 0) {
	mg::Message('', "---------------------------- DEMANDE FERMETURE PORTE ------------------------------------");
		mg::LampeCouleur($equipLampeCouleur, $intensiteSignalPorte, mg::ROUGE);
		if (!$demandeFaite) {
			($saison == 'HIVER') ? $sens = 'redescendue' : $sens = 'remontée';
			mg::message($destinataires, "@La température moyenne extérieure est $sens à $tempExt °, veuillez fermer le salon.");
			mg::setVar('_DemandeFaite', 1);
		}
	} else {
			mg::LampeCouleur($equipLampeCouleur, 0);
			mg::unsetVar('_DemandeFaite');
		}
}

?>