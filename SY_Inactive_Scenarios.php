<?php
/**********************************************************************************************************************
SY_Inactive_Scenarios - 193

Change le Nom Jeedom et la clef d'install
Desactive tous les scénario (sauf celui ci) si l'IP courante correspond à la machine de test.
Arrête tous les daemon des plugins.

**********************************************************************************************************************/

// Infos, Commandes et Equipements :

// N° des scénarios :

// Variables :
//	$ipStationTest = '192.168.2.19';
//	$clefInstallTest = 'b7e969cc0f9083dc680b14cdb5be0cdf3313f7e12fa1e02ab917124f13191e7';
//	$nomStationTest = 'TEST';
//	$scenarioAction = 'deactivate';
//	$pluginActif = 0; // 0 pour désactiver, 1 pour activer
//	$pluginExclus = "virtual, htmldisplay, script, jeexplorer, pimpJeedom, , camera";

// Paramètres :

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
	global $scenario;
	$values = array();

// SI L'ON EST SUR LA STATION DE TEST
$IP = mg::getConfigJeedom('core','internalAddr');
if ($IP == $ipStationTest) {
	mg::popupJeedom("
***** ATTENTION *****
Le nom de la machine et la clef d'installation vont être changés 
TOUS les scénarios et TOUS les plugins vont être désactivés 
60 secondes APRES la validation de cette fenêtre !!!");
	sleep(60);

	// modif clef d'install
	$sql = "UPDATE `config` set `value`= '$clefInstallTest'	 WHERE `plugin` = 'core' AND `key` = 'installKey'";
	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
		mg::message('', "****** Modification de l'installKey => $sql");

	// modif Nom Jeedom

	$sql = "UPDATE `config` set `value`= '$nomStationTest' WHERE `plugin` = 'core' AND `key` = 'name'";
	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
		mg::message('', "****** Modification du nom Jeedom => $sql");

	// Inhib des scénarios
	$scenarioID = $scenario->getID();
	$scenarios = scenario::all();
	foreach($scenarios as $scenario_) {
		$name = $scenario_->getHumanName();
		$id = $scenario_->getID();
		if ($id != $scenarioID) {
			mg::setScenario($id, $scenarioAction); // Desactive le scénario
		}
	}

	// Activation/Désactivation des plugins
	foreach (plugin::listPlugin(($pluginActif == 1 ? false : true)) as $plugin) {
		$plugin_id = trim($plugin->getId());
		if (strpos($pluginExclus, $plugin_id) !== false) { continue; }
		plugin::byid($plugin_id)->setIsEnable($pluginActif);
		mg::message('', "****** ".($pluginActif == 1 ? 'Activation' : 'Désactivation')." du plugin $plugin_id : $plugin_id");
	}
}

?>