<?php
/**********************************************************************************************************************
POST_Tabulator - 192
**********************************************************************************************************************/
global $scenario, $pathTabulator, $pathRessources, $nomTab, $prefHidden, $persistence, $identifiantKey, $identifiantKey, $tabTooltips, $tabTooltipsName, $selectChamp, $tabSavTabs;

// Infos, Commandes et Equipements :

// N° des scénarios :
	$scenario_alertes = 159; // N° du scénario de monitoring gérant la variable _alertes

//Variables :
	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
	$pathRessources = "$pathRef/ressources";
	$pathTabulator = "$pathRef/tabulator";
	$fileExportHTML = getRootPath() . "$pathTabulator/tabulator.html";
//	$fileExportJS = getRootPath() . "$pathTabulator/tabulator.js";

	$listTab = array('tabParams', 'tabVolets', 'tabUser', 'tabConso', 'tabChauffages', 'tabPassword', '	tabTooltips', '_alertes', 	'tabWidgets');

	$imgPoubelle = 'poubelle.png';
	$imgAdd = 'add.png';
	$imgWait = 'wait.gif';

// Paramètres :
	$tabTooltipsName = 'tabTooltips';	// Nom de la table de gestion des tooltips des tables
	$identifiantKey = 'KEY_';			// Préfixe de l'identifiant de colonne clef
	$prefHidden = '-';					// Préfixe de colonne pour ne pas l'afficher
	$persistence = mg::getParam('System', 'persistence');	// Mise en page persistante (via cookie ou local)

	$submit = mg::getTag('submit');
	$nomTab = mg::getTag('nomTab');
	$nbSegments = mg::getTag('nbSegments');
	$lngMessage = mg::getTag('lngMessage');
	$themeDefaut = 'themeCustom';

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
// LANCEMENT DIRECT DU SCRIPT
if (strpos($submit, '#') !== false) {
	$submit = 'Charge';
	$nomTab = $listTab[0];
	mg::messageT('', "Lancement direct avec $submit sur $nomTab");
}
$tab = mg::getVar($nomTab);
$nbKeys = getNbKeys($tab);	// passe pour nb de keys sur la table d'org

// ************************************ RESTAURATION DE LA TABLE A L'ETAT PRECEDENT ***********************************
if ($submit == 'RestaureLast') {
	mg::messageT('', "Restauration du dernier $nomTab"."_ en $nomTab en BdD");
	mg::setVar($nomTab, mg::getVar($nomTab.'_'));
}

// ************************************** RESTAURATION DE LA DERNIERE SAUVEGARDE **************************************
elseif ($submit == 'RestaureSav') {
	mg::messageT('', "Restauration du dernier $nomTab.json vers la BdD");
	$tmp = json_decode(file_get_contents(getRootPath() . "$pathTabulator/sav/$nomTab.json"));
//	mg::setVar($nomTab, $tmp);
		$scenario->setData($nomTab, $tmp);
}

// **************************************************** SAUVEGARDE ****************************************************
elseif ($submit == 'Sauvegarde') {
	mg::messageT('', "Sauvegarde en $nomTab"."__ de $nomTab en BdD");
	file_put_contents(getRootPath() . "$pathTabulator/sav/$nomTab.json", json_encode($tab));
	if ($nomTab == 'tabWidgets') {
		file_put_contents(getRootPath() . "$pathRef/widgets/$nomTab.json", "tabWidgets = '".json_encode($tab)."';");
	}
}

// ******************************************** ENREGISTREMENT DE LA TABLE ********************************************
elseif ($submit == 'Enregistre' && $lngMessage > 0) {
	mg::messageT('', "Sauvegarde préalable de $nomTab sous $nomTab"."_ en BdD et en .json pour les widgets");
	mg::setVar($nomTab.'_', $tab);
//	if ($nomTab == 'tabWidgets') {
//		file_put_contents(getRootPath() . "$pathRef/widgets/$nomTab.json", "tabWidgets = '".json_encode($tab)."';");
//	}

	// ************************************ Reconstruction et mémo du fichier POST ************************************
	$tabdata = '';
	for ($i=0; $i<$nbSegments; $i++) {
		$tabdata .= mg::getVar("_retFile".strval($i));
		mg::unsetVar("_retFile".strval($i*1));
	}
	mg::messageT('', "!Message origine : $lngMessage - Longueur du résultat " . strlen($tabdata));

	$tmp = array();
	$tabHead = array();

	// Lecture du fichier tabdata
	if ($tabdata[0] == ';') { $tabdata = '""'.$tabdata; } // Correction, ajout guillemet si champ vide au début (poignée handle ...)

	// ********************************************* TRANSPO en VAR JEEDOM ********************************************
	$tabdata = explode(PHP_EOL, $tabdata);
	for($i=0;$i<count($tabdata);$i++) {
		$tabLine = explode(';', trim($tabdata[$i]).';');
		if (!$tabHead) {
			$tabHead = $tabLine;
		}
		else {
			$key1 = ''; $key2 = ''; $champ = ''; $value = '';
			for($ii=0;$ii<count($tabHead);$ii++) {
				if ($tabHead[$ii] == '""' || $tabHead[$ii] == '') { continue; } // Si pas de nom de colonne on saute

				if (strpos($tabHead[$ii], $identifiantKey) !== false) {
					if (trim($tabLine[$ii], '"') != '') { $key[$ii] = trim($tabLine[$ii], '"'); }
				} else {
					$champ = trim($tabHead[$ii], '""');
					$value = trim($tabLine[$ii], '""');

					// Seul les tableaux de 1 à 4 clef sont géré
					if ($nbKeys == 1) {
						$tmp[$key[1]][$champ] = $value;
					} elseif ($nbKeys == 2) {
						$tmp[$key[1]][$key[2]][$champ] = $value;
					} elseif ($nbKeys == 3) {
						$tmp[$key[1]][$key[2]][$key[3]][$champ] = $value;
					} elseif ($nbKeys == 4) {
						$tmp[$key[1]][$key[2]][$key[3]][$key[4]][$champ] = $value;
					} else { $tmp[$champ] = $value; }
				}
			}
		}
	}
//	mg::message('', print_r($tmp, true));
	mg::setVar($nomTab, $tmp);
	if ($nomTab == 'tabWidgets') {
		file_put_contents(getRootPath() . "$pathRef/widgets/$nomTab.json", "tabWidgets = '".json_encode($tmp)."';");
	}
	//mg::message('', print_r(mg::getVar($nomTab), true));
}

// ****************************************** CHARGE - ENREGISTRE - RESTAURE ******************************************
if ($submit == 'Charge' || $submit == 'Enregistre' || $submit == 'RestaureLast' || $submit == 'RestaureSav' || $submit == 'Sauvegarde') {
	$tabTooltips = mg::getVar($tabTooltipsName);
	$tab = mg::getVar($nomTab);
	$nbKeys = getNbKeys($tab);
	mg::messageT('', "Rechargement de la table '$nomTab' - Nombre de clé : $nbKeys.");

	// ************************************* REGENERATION DE LA TABLE '_alertes' **************************************
	if ($nomTab == '_alertes') {
		mg::setScenario($scenario_alertes, 'start', "nomTab=_alertes action=$submit");
		mg::wait("scenario($scenario_alertes) == 0", 180);
	}

	// ******************************************** GENERATION DU TABULATOR *******************************************
	mg::messageT('', "! GENERATION DU TABULATOR DE $nomTab");

	$id = 1;
	$value = '';
	transpoBDD($tab, $enTeteKey, $id, $keyCourante, $name, $value, $tabledata, $columns);

	mg::message('', print_r($tabledata, true));
	mg::message('', '*********************************************************************');
	mg::message('', print_r($columns, true));

	mg::message('', '*********************************************************************');
	$groupBy = ($nbKeys > 1 ? $identifiantKey.'1' : '');
} // Fin de charger

//********************************************************************************************************************
$txtHTML = txtHTML($nomTab, $listTab, $nbKeys, $id-1, $selectChamp, $themeDefaut, $pathTabulator, $pathRessources);
$txtHTML .= '<script>';
$txtHTML .= scriptJS($nomTab, $tabledata, $columns, $groupBy, $themeDefaut, $persistence, $identifiantKey, $imgWait);
$txtHTML .= '</script>';
file_put_contents($fileExportHTML, $txtHTML);
//mg::message('', $txtHTML);

mg::setVar($tabTooltipsName, $tabTooltips);

// ********************** Signale à 'HtmlEditTable_POST.php' que le traitement est terminé ****************************
mg::setVar('_htmlOK', '1');

// *********************************************************************************************************************
// *************************************** TRANSPOSITION EN TABULATOR (recursif) ***************************************
// *********************************************************************************************************************
function transpoBDD($tab, &$enTeteKey, &$id, &$keyCourante, &$name, $value, &$tabledata, &$columns) {
	global $nomTab, $prefHidden, $identifiantKey, $tabTooltips, $selectChamp;
	$enTete = '';
//	mg::message('', " GENERATION DU TABULATOR DE $nomTab - id : $id - keyCourante : $keyCourante");

	$count = count($tab);
	foreach($tab as $name => $value) {

		// DEBUT CHAMP CLEFS
		if (is_array($value)) {
			$keyCourante++;
			$value2 = $identifiantKey.$keyCourante;
			$enTeteKey[$keyCourante] = "$value2:'$name', ";
			$columns .= getColumns($id, $value2, $value2);
			transpoBDD($value, $enTeteKey, $id, $keyCourante, $name, $value, $tabledata, $columns);
			$keyCourante--;
		// FIN CHAMP CLEFS
		} else {
			$columns .= getColumns($id, $name, $value);
			$count--; // se termine à '0')
			// DEBUT CHAMP HORS CLEF
			if ($count+1 == count($tab)) {
				for ($i=1;$i<=count($enTeteKey);$i++) { $enTete .= $enTeteKey[$i]; }
				$tabledata .= "{id:$id, ".$enTete;
			}
			$value = str_replace("'", "\'", $value); // Echappement des apostrophe
			$tabledata .= "'$name':'$value', ";
			// FIN CHAMP HORS CLEF
			if ($count == 0) {
				$tabledata .= '},'.PHP_EOL;
				$id++;
			}
		}
	}
}

// ********************************************************************************************************************
// **************************************************** GET COLUMNS ***************************************************
// ********************************************************************************************************************
	function getColumns($id, $name, $value) {
		global $nomTab, $prefHidden, $identifiantKey, $tabTooltipsName, $tabTooltips, $selectChamp;
		$columns = '';
		$champKey = strpos($name, $identifiantKey);

		if ($id==1) {
			mg::message('', "*************$id - $name - $value");
			addTabTooltips($name);

			$columns .= '{title:"'.$name.'"'.' , field:"'.$name.'"'
			.' , headerTooltip: '.'"'.$name.' - '.str_replace("'", "\'", $tabTooltips[$nomTab][$name]['tooltip']).'"'
			.' , headerHozAlign:"center"'
			. (strpos($name, $prefHidden) !== false ? ', visible:false, download:true' : '') // Cache la colonne

			// Format hérité de la table tooltips
			. (($tabTooltips[$nomTab][$name]['filtre'] == 'true') ? ', headerFilter:"input"' : '') // Champ filtre sur en tete de colonne
			. (($tabTooltips[$nomTab][$name]['type'] == 'boolean') ? ', hozAlign:"center", editor:true, formatter:"tickCross", minWidth:30' : '')
			. (($tabTooltips[$nomTab][$name]['type'] == 'date') ? ', hozAlign:"center", minWidth:155' : '')
			. (($tabTooltips[$nomTab][$name]['type'] == 'number') ? ', hozAlign:"center", sorter:"number", editor:"input", validator:"numeric"' : '')
			. (($tabTooltips[$nomTab][$name]['type'] == 'integer') ? ', hozAlign:"center", sorter:"number", editor:"input", minWidth:50, validator:"integer"' : '')
			. (($tabTooltips[$nomTab][$name]['type'] == 'string' || $tabTooltips[$nomTab][$name]['type'] == '') ? ', hozAlign:"center", sorter:"string", editor:"input"' : '')
			. (($tabTooltips[$nomTab][$name]['width'] > 0) ? ', width:"'.$tabTooltips[$nomTab][$name]["width"].'"' : '') // Champ filtre sur en tete de colonne

			// Spécifique pour les clefs
			. ($champKey !== false ? ', minWidth:100' : '')
			. ($champKey !== false ? ', hozAlign:"left"' : ', hozAlign:"center"')
			. ($champKey !== false ? ', frozen:true' : '')
			. ($champKey === false ? ', headerContextMenu:headerContextMenu' : '')

			// Spécifique pour table Tooltips
			. (($nomTab == $tabTooltipsName && $name == 'type') ? ', editor:"select", editorParams:{values:{"":"nul", "string":"String", "boolean":"Boolean", "number":"Number", "integer":"Entier", "date":"Date"}}' : '')

			.'}, '.PHP_EOL;
		}
		return $columns;
	}

// ********************************************************************************************************************
// *********************************************** INIT CHAMP TAB TOOLTIP *********************************************
// ********************************************************************************************************************
function addTabTooltips($nomChamp) {
	global $nomTab, $tabTooltips;
	if (!isset($tabTooltips[$nomTab][$nomChamp]['tooltip'])) { $tabTooltips[$nomTab][$nomChamp]['tooltip'] = ' '; }
	if (!isset($tabTooltips[$nomTab][$nomChamp]['type'])) {	$tabTooltips[$nomTab][$nomChamp]['type'] = 'string'; }
	if (!isset($tabTooltips[$nomTab][$nomChamp]['filtre'])) { $tabTooltips[$nomTab][$nomChamp]['filtre'] = 'false'; }
	if (!isset($tabTooltips[$nomTab][$nomChamp]['width'])) { $tabTooltips[$nomTab][$nomChamp]['width'] = ''; }
}

// ***************************************************** NB KEYS ******************************************************
/* Retourne le nb de clef du tableau passé en paramètre.															 */
// ********************************************************************************************************************
function getNbKeys($tab, &$nbKeys=0) {
	foreach($tab as $clef1 => $tab1) {
		if (is_array($tab1)) {
			$nbKeys++;
			getNbKeys($tab1, $nbKeys);
			return $nbKeys;
		}
	}
	return $nbKeys;
}

// ********************************************************************************************************************
// ************************************************ GENERATION DU HTML ************************************************
// ********************************************************************************************************************

function txtHTML($nomTab, $listTab, $nbKeys, $nbLgn, $selectChamp, $themeDefaut, $pathTabulator, $pathRessources) {
	global $scenario;
	$IP = mg::getTag('IP');
	$apikey = mg::getConfigJeedom('core');
	$numScenario = $scenario->getID();
	$date = date('G\h\ i\m\n', time());
	$lastSav = date('d\/m\/Y \à H\hi\m\n', filemtime (getRootPath() . "$pathTabulator/sav/$nomTab.json"));
	$nbSelect = 1;
	$select = '';
	for($i=0; $i<count($listTab);$i++) {
		$tab = $listTab[$i];
		if (trim($nomTab) != trim($tab)) {
			$select .= "<option value='$tab'>$tab</option>\n";
		} else {
			$select .= "<option selected value='$tab'>$tab</option>\n";
			$tabOrg = $tab;
		}
	}
	$titre = "$nbLgn lignes - ".strtoupper($nomTab)." - $date";

$txtHTML = "
<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Strict//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'>
<html lang='fr'>
	<head>
		<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />

		<link href='$pathRessources/tabulator-master/dist/css/tabulator.min.css' rel='stylesheet'>
		<!-- LISTE DES CHOIX DE THEME DU TABLEAU -->
			<link rel='alternate stylesheet' type='text/css' title='themeCustom' href='$pathTabulator/theme_Custom.css'/>
			<link rel='stylesheet' type='text/css' title='themeMidnight' href='$pathRessources/tabulator-master/dist/css/tabulator_midnight.min.css' rel='stylesheet' />
			<link rel='alternate stylesheet' type='text/css' title='themeStandard' href='$pathRessources/tabulator-master/dist/css/tabulator.min.css' rel='stylesheet'/>
		<link href='tabulator.css' rel='stylesheet'>
	</head>

	<body>
		<!-- ****************************************** TITRE DU TABLEAU ****************************************** -->
		<table id='titre' class='teteTab' width='100%'>
			<tr>
				<td width='25%' align='left'
				</td>
				<td width='50%' align='center'>
					<div class='titrePage'>$titre
					<button class='bt-reload' value='Reload' id='reload' onclick='reload();' title='Recharge la page, souvent necessaire après une longue inactivité.'>--- Reload ---</button>
					</div>
				</td>
				<td width='25%' align='right'>
					<span style='font-size:20px;'>Sauvegardé le $lastSav.
				</td>
			<tr>
		</table>
		<div id='imgWait' class='titrePage'> </div>

		<!-- ************************************** TABLEAU POUR LES BOUTONS ************************************** -->
		<table id='Boutons' class='teteTab' width='100%''>
			<tr>
				<td width='25%' align='left'>
					<button id='reset' class='bt-rest' title='Supprime tous les filtres.'>Reset</button>
					*** Ctrl-Maj-Suppr Efface le cache ***
				</td>


				<td width='20%' align='center'>
					<select id='choixTheme' style='font-size:16px;'>
						<option></option>
						<option selected value='themeCustom'>Thème Custom</option>
						<option value='themeMidnight'>Thème Midnight</option>
						<option value='themeStandard'>Thème standard</option>
					  </select>
				</td>

				<td width='55%' align='right'>
					<!-- ********************************************************************** -->
					<FORM method=post action='http://$IP$pathTabulator/TAB_tabulator_POST.php' enctype='application/x-www-form-urlencoded' onsubmit='return appelJeedom()'>
						<input	type='submit' class='bt-rest' name='submit' value='RestaureSav' title='Restauration de la dernière sauvegarde .json du $lastSav.'>
						<input	type='submit' class='bt-rest' name='submit' value='RestaureLast' title='Restauration de la version précédent le dernier enregistrement.'>

						<input	type='submit' class='bt-charge' name='submit' value='Charge'>
						<input type='hidden' id='message' name='message'>
						<input type='hidden' name='nomTabOrg' value=$tabOrg>
						<input type='hidden' name='IP' value=$IP>
						<input type='hidden' name='apikey' value='$apikey'>
						<input type='hidden' name='numScenario' value=$numScenario>
						<SELECT name='nomTab' class='select' size='$nbSelect' style='font-size:16px;'>
							$select
						</SELECT>
						<input	type='submit' class='bt-enr' name='submit' value='Enregistre'>
						<input	type='submit' class='bt-sav' name='submit' value='Sauvegarde' title='Sauvegarde .json sur disque.'>
					</FORM>
					<!-- ********************************************************************** -->
				</td>
			</tr>
		</table>

		<!-- ******************************************* TAB_TABULATOR ******************************************** -->
		<div id='$nomTab' class='tabTabulator'></div>

<script type='text/javascript' src='$pathRessources/tabulator-master/dist/js/tabulator.min.js'></script>
<!-- <script type='text/javascript' src='$pathTabulator/tabulator.js'></script> -->
";
return $txtHTML;
}

// ********************************************************************************************************************
// ********************************************** GENERATION DU SCRIPT JS *********************************************
// ********************************************************************************************************************
function scriptJS($nomTab, $tabledata, $columns, $groupBy, $themeDefaut, $persistence, $identifiantKey, $imgWait) {
	$key1 = $identifiantKey.'1';
	$etatPersistence = ($persistence ? 'true' : 'false');
	$persistence = ($persistence ? "'$persistence'" : 'false');

	$scriptJS = "
	twChangeStyle('$themeDefaut');

/******************************************************** APPEL JEEDOM **********************************************/
		appelJeedom = function() {
			table.clearFilter(true); // Efface TOUS les filtres par sécurité

			// Affichage image d'attente
			var nouvelleimage=document.createElement('IMG');
			document.getElementById('imgWait').appendChild(nouvelleimage);
			document.getElementById('imgWait').lastChild.setAttribute('src','$imgWait');

			table.download('csv', 'data.csv', {delimiter:';'});
			//console.log(tbldata);
			//console.log(tbldata.length + ' octets.');

/*			if (tbldata.length < 10) {
				alert ('Tableau < 10 octets, Annulation de l\'enregistrement !!!')
				return false;
			}*/
			document.getElementById('message').value = tbldata;
			return true;
		}

/******************************************************* BOUTON DE Reload ********************************************/
		function reload() { document.location.reload(); }

/******************************************************** BOUTON DE Reset ********************************************/
// button click de reset
document.getElementById('reset').addEventListener('click', function(){
table.clearSort();
table.clearFilter(true);
});

/*************************************************** CHANGEMENT DE THEME *********************************************/
document.getElementById('choixTheme').addEventListener('change', twChangeStyle);
// Fonction pour changer le thème
function twChangeStyle(sTitre) {
	var i, a;
	if ((typeof sTitre === 'object' && sTitre !== null) || typeof sTitre === 'function') {
		sTitre = document.getElementById('choixTheme').value;
	}
	// Boucle tout les élément « link » du document.
	for(i=0; (a = document.getElementsByTagName('link')[i]); i++) {
		// Si l’élément est à un attribut « rel » et qu’il contient un titre.
		if(a.getAttribute('rel').indexOf('style') != -1 && a.getAttribute('title')) {
			// Désactive la feuille de style
			a.disabled = true;
			// Active la feuille de style avec le bon titre.
			if(a.getAttribute('title') == sTitre) a.disabled = false;
		}
	}
}

/************************************************ BOUTONS ADD-DEL LIGNES *********************************************/
 var bt_AddLigne = function(cell, formatterParams, onRendered){ //plain text value
return	'<img src=\"add.png\">';
};
var addLigne = function(cell){
	table.addRow({ $key1 :cell.getRow().getData().$key1, /*type:'inconnu'*/}, true, cell.getRow().getData().id);
};

 var bt_DelLigne = function(cell, formatterParams, onRendered){ //plain text value
return	'<img src=\"poubelle.png\">';
};
var delLigne = function(cell){
		table.deleteRow(cell.getRow().getData().id);
};

/****************************************************** ZONE DATAS ***************************************************/
var tabledata = [
$tabledata
];

/***************************************************** HEADER MENU ***************************************************/
//var headerMenu = [
var headerContextMenu = [
	{ label:'move Colonne <= left', action:function(e, column){
		column.move(column.getPrevColumn().getField(), false);
	}},
	{ label:'Ajoute Colonne', action:function(e, column){
		var newName = prompt('Quel est le nom de la nouvelle colonne ?');
		if (newName.length > 2) {
			table.addColumn({title:newName, field:newName, editor:'input', headerContextMenu:headerContextMenu}, false, column.getField());
		}
	}},
	{ label:'move Colonne => right', action:function(e, column){
		column.move(column.getNextColumn().getField(), true);
	}},
	{ label:'Efface Colonne', action:function(e, column){
		if ( confirm( 'Etes vous sur de vouloir supprimer la colonne \"'+column.getField()+'\" ?') ) {
			column.delete();
		} else {
			return;
		}
	}},
	{ label:'Renomme Colonne', action:function(e, column){
		var newName = prompt('Quel est le nouveau nom pour la colonne \"'+column.getField()+'\" ?');
		if (newName.length > 2) {
			column.updateDefinition({title:newName}) //change the column title
		} else {
			return;
		}
	}},
]

/************************************************* DECLARATION TABLE *************************************************/
	var table = new Tabulator('#$nomTab', {

	downloadReady:function(fileContents, blob){
		tbldata = fileContents;
   return false; //must return a blob to proceed with the download, return false to abort download
},

//		height: (window.innerHeight/*-100*/)+'px',
//		maxHeight:(window.innerHeight-100)+'px', //'100%',
		//pagination:'local', //enable local pagination.
		data:tabledata, //load initial data into table
		layout:'fitColumns', // fitData - fitColumns - fitDataFill - fitDataStretch - fitDataTable	//fit columns to width of table (optional)
		cellHozAlign:'center', //center align cell contents
		headerSort:true, //disable header sort for all columns
		history:true, //record table history
		// autoColumns: true,
		 validationMode:'blocking', // highlight - blocking

		resizableColumns:true, // this option takes a boolean value (default = true)
		autoResize:true,  // prevent auto resizing of table

		persistence:$etatPersistence,
		persistenceMode:$persistence, //store persistence information in a 'cookie' or 'local'
		persistence:{columns: true, filter: false,	sort: false, group: false, page: false, }, //persist column layout

		movableRows:true,

		tooltipsHeader:true, //enable header tooltips

		groupBy:'$groupBy',
		groupHeader: function(value, count, data, group){
			//value - the value all members of this group share
			//count - the number of rows in this group
			//data - an array of all the row data objects in this group
			//group - the group component for the group
		   return value + \"<center><span style='color:red; margin-left:10px;'>\" + '***** ' + value.toUpperCase() + ' - ' + count + ' items *****</center></span>';
		},

/****************************************************** ZONE HEADER **************************************************/
		// Zone Header
		columns:[ //Define Table Columns
		   {rowHandle:true, formatter:'handle', headerSort:false, frozen:true, width:25, minWidth:30},
		$columns
		{formatter:bt_AddLigne, width:25, minWidth:35, hozAlign:'center', cellClick:function(e, cell){addLigne(cell)}, headerSort:false},
		{formatter:bt_DelLigne, width:25, minWidth:35, hozAlign:'center', cellClick:function(e, cell){delLigne(cell)}, headerSort:false},
		], // Fin columns
	}); // Fin newTabulator

/*********************************************************************************************************************/
";
return $scriptJS;
}
?>