<?php
/************************************************************************************************************************
Tetes Scenarios - 129
Génère le fichier mg_Tetes_Scenarios
************************************************************************************************************************/

// Infos, Commandes et Equipements :

// N° des scénarios :

//Variables :
	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
	$fileExport = (getRootPath() . "$pathRef/util/_Export_Var_et_Scenarios.txt");
	$separateur ="\n/***********************************************************************************************************************/\n";

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');

/***********************************************************************************************************************/
/***********************************************************************************************************************/
/***********************************************************************************************************************/
$exportTxt = "<?php\n";

$__CLASS__ = 'dataStore';
$values = array('type' => 0);
$sql = 'SELECT * FROM `dataStore` ORDER BY `key`';
$dataStore = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, $__CLASS__);

//-----------------------------------------------------------------------------------------------------------------------
$dateExport = str_repeat("\t", 13) . "EXPORT DU " . date('d\/m\/Y \à H\hi\m\n', time());
$exportTxt .=  $separateur;
$exportTxt .=  $dateExport;
$exportTxt .=  $separateur;

$nom = str_repeat("\t", 13) . "Liste des variables Jeedom";
$i = 0;
$exportTxt .=  $separateur;
$exportTxt .=  $nom;
$exportTxt .=  $separateur;

foreach ($dataStore as $variable) {
	$tmp = serialize($variable);
	$tmp2 = explode(';', $tmp);

	$NomVar = explode('"', $tmp2[7]);
	$NomVar = trim($NomVar[1], '"');

	$valueVar = explode('"', $tmp2[9]);
	$valueVar = trim($valueVar[1], '"');
	$valueVar = str_replace("", "_", $valueVar);
	if ($valueVar == "{") { $valueVar = "[object Object]"; }

	$exportTxt .= "$NomVar ==> '" . trim(substr($valueVar, 0, 60)) . "'\n";
	$i++;
}
$exportTxt .=  "\n";
$exportTxt .=  str_repeat("\t", 13) . "$i Variables Jeedom.\n";

mg::MessageT('', "-----------------------Export des variables terminés. ------------------------------");

//-----------------------------------------------------------------------------------------------------------------------

//-----------------------------------------------------------------------------------------------------------------------
// Export des scénario
foreach ($scenario->all() as $scenario) {

	$export = $scenario->export('array');

	$blocCode = $export['elements'][0]['subElements'][0]['expressions'][0]['expression']; 
//		print_r($export); 

		// On filtre les scénarios sur l'existence d'une ligne d'étoile en tête du bloc code
	if (strpos($blocCode, '******************') !== false) {
		// Calcul du nom du scénario
		$nom = str_repeat("\t", 13) . "function " . str_replace('-', '', str_replace(' ', '_', $export["name"])) . "()";
		$exportTxt .=  $separateur;
		$exportTxt .=  $nom;
		$exportTxt .=  $separateur;
mg::message('', $exportTxt);

		// Calcul du schedule
		$schedule = $export['schedule'];
		if (!is_array($schedule)) { $exportTxt .=  "Sheduler : $schedule\n\n"; }

		// Calcul des déclencheurs
		if (is_array($export['trigger'])) {
			foreach ($export['trigger'] as $key => $trigger) {
				if (trim((string)$trigger) != '') { $exportTxt .= "Trigger ($key) : $trigger\n"; }
			}
		}

		// Export du bloc code
		$exportTxt .= "\n$blocCode\n";
	}
}
$exportTxt .= "\n?>";

file_put_contents($fileExport, $exportTxt);

mg::MessageT('', "-----------------------Export des scénarios terminés. ------------------------------");

?>
