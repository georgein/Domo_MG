<?php
/**********************************************************************************************************************
Ouvertures Salon - 121
Fait une demande lumineuse pour ouvrir ou fermer les portes selon les températures salon/Extérieure ET SI nuitSalon != 2.
Enchaîne au final le scénario ChauffageMode
**********************************************************************************************************************/

// Infos, Commandes et Equipements
//	$infTempExt, $infTempSalon, $infNbPortesSalon
//	$equipLampeCouleur

// N° des scénarios :

//Variables :
	$tabChauffagesTmp = mg::getVar('tabChauffagesTmp');

	$saison = mg::getVar('Saison');
	$alarme = mg::getVar('Alarme');
	$nuitSalon = mg::getVar('NuitSalon');
	$nuitExt = mg::getVar('NuitExt');
	$destinataires = mg::getParam('Chauffages','destinatairesPortes');			// Destinataire du message d'annonce
	
// Paramètres :
	$tempSeuilPorte = mg::getParam('Chauffages','tempSeuilPorte');				// Différence de température maximum pour signal lumineux d'ouverture/fermeture de portes
	$intensiteSignalPorte = mg::getParam('Chauffages','intensiteSignalPorte');	// Intensité de la lampe couleur

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
// Sortie si NuitSalon == 2 ou en alarme
if ( $nuitSalon == 2) {
		mg::LampeCouleur($equipLampeCouleur, 0);
		return;
	}
mg::setCron('', '*/5 * * * *');

// Pause pour éviter les faux signaux en cas d'ouverture/fermeture ponctuelle
if (mg::declencheur('NbPortes') && mg::getCmd($infNbPortesSalon) > 0) {
	sleep (30);
}

$demandeFaite = mg::getVar('_DemandeFaite', 0);
$nbPortesSalon = mg::getCmd($infNbPortesSalon);
$consigne = mg::getCmd("#[Salon][Températures][Consigne]#");
$tempSalon = mg::getCmd($infTempSalon);
$tempExt = mg::getCmd($infTempExt);
$difference = $consigne - $tempExt;

mg::MessageT('', "Saison : $saison - (TempExtérieure : $tempExt - TempSalon/Consigne : $tempSalon / $consigne) => (TempSeuilPorte : $tempSeuilPorte - Différence avec Ext. : $difference");

// Sortie si différence non significative
if (abs($difference) < $tempSeuilPorte) {
	mg::LampeCouleur($equipLampeCouleur, 0);
	mg::unsetVar('_DemandeFaite');
	return;
}

// Gestion d'ouverture de porte
if ($nbPortesSalon == 0) {
	if ( ($saison == 'ETE' && $difference  > 0 && $tempSalon > $consigne) 
			|| ($saison == 'HIVER' && $difference < 0 && $tempSalon < $consigne) ) {
		mg::Message('', "---------------------------- DEMANDE OUVERTURE PORTE ------------------------------------");
			mg::LampeCouleur($equipLampeCouleur, $intensiteSignalPorte, mg::VERT);
			if (!$demandeFaite) {
				($saison == 'HIVER') ? $sens = 'remontée' : $sens = 'descendue';
				mg::message($destinataires, "@La température moyenne extérieure est $sens à $tempExt °, veuillez ouvrir le salon.");
				mg::setVar('_DemandeFaite', 1);
			}
		} else {
			mg::LampeCouleur($equipLampeCouleur, 0);
			mg::unsetVar('_DemandeFaite');
		}
}
// Gestion de fermeture de porte
else {
	if ( ($saison == 'ETE' && $difference  < 0 && $tempSalon > $consigne) 
			|| ($saison == 'HIVER' && $difference > 0 && $tempSalon < $consigne) ) {
		mg::Message('', "---------------------------- DEMANDE FERMETURE PORTE ------------------------------------");
			mg::LampeCouleur($equipLampeCouleur, $intensiteSignalPorte, mg::ROUGE);
			if (!$demandeFaite) {
				($saison == 'HIVER') ? $sens = 'redescendue' : $sens = 'montée';
				mg::message($destinataires, "@La température moyenne extérieure est $sens à $tempExt °, veuillez fermer le salon.");
				mg::setVar('_DemandeFaite', 1);
			}
		} else {
			mg::LampeCouleur($equipLampeCouleur, 0);
			mg::unsetVar('_DemandeFaite');
		}
}

?>