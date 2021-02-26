<?php

/**********************************************************************************************************************
Paramètragess_137()

Crée les variable Jeedom du tableau de paramètrages issues du fichier .ini
Divers scénarios d'init sont ensuite appelé.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :

//N° des scénarios :

// Variables :
	$FileParam_Ini = getRootPath() . "/mg/PA_Parametrages.ini"; // Le fichier contenant la définition des paramètres
	$LogTimeLine = mg::getParams('Log', 'timeLine');
	
	$TabScenarios = array	(							// Tableaux des scénarios de définition des tableaux de paramètrages.
							142,	// Tab_Conso
							139,	// Tab_Volets
							140,	// Tab_Zones (Chauffages)
							165,	// Tab_User (Users)
														// Tableaux des scénarios d'initialisation des 'grandes' routines.
/*							104,	// Chauffage Mode
							59,		// Luminosité Salon
							69,		// Luminosité Extérieure*/
						);

//Paramètres :

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
sleep(2); // tempo pour éviter les appels multiples d'incron lors de la mise à jour
$i = 0;

/*mg::unsetVar('tabParams');
$tabParams = parse_ini_file($FileParam_Ini, true);
mg::setVar('tabParams', $tabParams);
mg::message('', print_r($tabParams, true));
mg::Message($LogTimeLine, "Param - Mise à jour des paramètres généraux effectuée.");

mg::setVar('_seuilVentFort', mg::getParams('Confort', 'seuilVentFort'));
mg::Message($LogTimeLine, "Param - Pose du virtuel _seuilVentFort effectuée.");
*/
// Lancement des scénario d'init divers
foreach ($TabScenarios as $key => $scenario_id) {
	mg::setScenario($scenario_id, 'start');
	mg::getScenario($scenario_id, $name);
}
//mg::Message($LogTimeLine, "Param - Lancement des scénarios d'init/maintenance effectué.");
// ***** INIT de la table PASSWORD *****
	$Tab_Password = mg::getVar('tabPassword');
$Tab_Password = array(
'Options' => array('mdp' => '31416#'),
'MG' => array('mdp' => '#31416'),
'NR' => array('mdp' => '121060'),
'PS' => array('mdp' => '#12345'),
'CB' => array('mdp' => '081147'),
'Invité' => array('mdp' => '#1234#'),
'RETOUR' => array('mdp' => '!')
);

mg::setVar('tabPassword', $Tab_Password);
?>