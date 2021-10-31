<?php
/**********************************************************************************************************************
Chauffage Chauffages Auto-Forcé - 112
Gestion globale des chauffages en mode automatique, ou forcé en Eco (HG après $timingHG heures suivant le mode Eco).
Positionner la variable '_TypeChauffage' à Auto (Confort si HeureFin existe, Eco sinon), Eco ou HG avant de lancer le scénario.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :

// N° des scénarios :
	$scenChauffageMode = 104;								// Scénario de gestion du chauffage du salon

//Variables :

// Paramètres :
	$logChauffage = mg::getParam('Log', 'chauffage');		// Pour debug

/******************************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$typeChauffage = mg::getVar('_TypeChauffage', 'Auto');

// Passage en mode Auto (le script ModeChauffage remettra le bon mode à son lancement)
if ($typeChauffage == 'Auto' || $typeChauffage == 'Confort') {
	mg::setScenario($scenChauffageMode, 'Activate');
	mg::setScenario($scenChauffageMode, 'start');
	$message = "RETOUR des chauffages en mode $typeChauffage";

// Passage en mode Eco
} else {
	$typeChauffage = 'Eco';
	mg::setScenario($scenChauffageMode, 'Deactivate');
	$message = "Passage FORCE des chauffages en mode $typeChauffage si nécessaire.";
}

mg::messageT($logChauffage, $message);

	foreach(eqLogic::byType('thermostat') as $thermostat){
		$equipement = $thermostat->getHumanName();
		if (!$thermostat->getIsEnable()) continue;
		if (mg::getCmd($equipement, 'Mode') == 'Off') continue;
		
	// On force à Eco si nécessaire
	if ($typeChauffage != 'Auto' && $typeChauffage != 'Confort' && mg::getCmd($equipement, 'Mode') != $typeChauffage) {
		mg::setCmd($equipement, $typeChauffage);
	}
}

mg::unsetVar('_TypeChauffage');

?>