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
	$tabChauffages = (array)mg::getVar('tabChauffages');
	$tabChauffages_ = mg::getVar('_tabChauffages');

// Paramètres :
	$logChauffage = mg::getParam('Log', 'chauffage');				// Pour debug
	$timingHG = mg::getParam('Chauffages','timingHG');		// TimingHG de passage à HG après le forçage du mode Eco

/******************************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$typeChauffage = mg::getVar('_TypeChauffage', 'Auto');

// Retablissement chauffage (retour à Confort)
if ($typeChauffage == 'Auto') {
	foreach ($tabChauffages as $nomChauffage => $detailsZone) {
		if (!$nomChauffage) {continue; }
		$__Mode	= '_Chauf' . $nomChauffage . 'Mode';
		if (!$detailsZone['chauffage'] == 0 || !$detailsZone['equip']) { continue; }
			$tabChauffages_[$nomChauffage]['mode'] = 'Confort'; 
	}
	mg::setScenario($scenChauffageMode, 'Activate');
	mg::setScenario($scenChauffageMode, 'start');
	mg::unsetVar('_TypeChauffage');
	mg::message($logChauffage, "Retour forcé des chauffages en mode Auto");
	mg::setCron('', time() - 24*3600); // Annulation du cron
}

// Passage en mode Eco
else if ($typeChauffage == 'Eco') {
	mg::setScenario($scenChauffageMode, 'Deactivate');
	foreach ($tabChauffages as $nomChauffage => $detailsZone) {
		if (!$nomChauffage) {continue; }
		if (!$detailsZone['chauffage'] || !$detailsZone['equip']) { continue; }
		$tabChauffages_[$nomChauffage]['mode'] = 'Eco'; 
	}
	mg::message($logChauffage, "Passage forcé des chauffages en Eco");

	// Préparation relance dans $timingHG en mode HG
//	mg::setVar('_TypeChauffage', 'HG');
//	mg::setCron('', time() + ($timingHG * 3600));
}

// Passage en mode HG 
else if ($typeChauffage == 'HG') {
	mg::setScenario($scenChauffageMode, 'Deactivate');
	foreach ($tabChauffages as $nomChauffage => $detailsZone) {
		if (!$nomChauffage) {continue; }
		if (!$detailsZone['chauffage'] == 0 || !$detailsZone['equip']) { continue; }
		$tabChauffages_[$nomChauffage]['mode'] = 'HG'; 
	}
	mg::message($logChauffage, "Passage forcé des chauffages en HG");
}

?>