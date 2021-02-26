<?php
/**********************************************************************************************************************
_test_tableau - 190
**********************************************************************************************************************/
global $scenario;

// Infos, Commandes et Equipements :

// N° des scénarios :
	$scenario_alertes = 159; // N° du scénario de monitoring gérant la variable _alertes
	
//Variables :
	$FileExport = (getRootPath() . '/mg/HtmEditTable/HtmEditTable.html');
	$listTab = array('tabParams', 'tabVolets', 'tabUser', 'tabConso', 'tabChauffages', 'tabPassword', '	tabTooltips', '_alertes');
	
	$imgTooltip = 'interro.png';
	$imgPoubelle = 'poubelle.png';
	$tabTooltipsName = 'tabTooltips';	// Nom de la table de gestion des tooltips des tables
	$refresh = 1;						// Période de rafraichissement au retour du post
	$minified = ''; //'.mn';			// mettre '' pour utiliser les fichiers NON minifiés
	$prefHidden = '-';					// Préfixe de colonne permettant de ne pas l'afficher - NE PAS UTILISER !!! CAR CELA 'PERD' CES COLONNES A L'ENREGISTREMENT
	$apikey = mg::getAPI('core');
	$numScenario = $scenario->getID();

// Paramètres :
	$submit = mg::getTag('submit');
	$nomTab = mg::getTag('nomTab');
	$nbSegments = mg::getTag('nbSegments');
	$lngMessage = mg::getTag('lngMessage');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
// LANCEMENT DIRECT DU SCRIPT
if (strpos($submit, '#') !== false) {
	$submit = 'Charger';
	$nomTab = $listTab[0];
	mg::messageT('', "Lancement direct avec $submit sur $nomTab");
}

$tabTooltips = mg::getVar($tabTooltipsName);
$tabParams = mg::getVar($nomTab);
$tabHead = getKey($tabParams, $prefHidden, $nbKeys); // passe pour nb de key sur format de fichier org

// ********************************************* RESTAURATION DE LA TABLE *********************************************
if ($submit == 'Restaurer') {
	mg::messageT('', "Restauration du dernier $nomTab_ en $nomTab en BdD");
	mg::setVar($nomTab, mg::getVar($nomTab.'_'));
}

// ******************************************** ENREGISTREMENT DE LA TABLE ********************************************
if ($submit == 'Enregistrer' && $lngMessage > 0) {
	mg::messageT('', "ENREGISTREMENT => Longueur du message : $lngMessage octets - nomTab : $nomTab - submit : $submit - Nb de segments : $nbSegments");
	mg::messageT('', "Sauvegarde préalable de $nomTab sous $nomTab"."_ en BdD");
	mg::setVar($nomTab.'_', $tabParams);

	// ************************************ Reconstruction et mémo du fichier POST ************************************
	$tmp = '';
	for ($i=0; $i<$nbSegments; $i++) {
		$tmp .= mg::getVar("_retFile".strval($i*1));
		mg::unsetVar("_retFile".strval($i*1));
	}

	mg::messageT('', "!Message origine : $lngMessage - Longueur du résultat " . strlen($tmp));

	// ********************************************* TRANSPO en VAR JEEDOM ********************************************
		unset($tabParams);
		$lignes = explode('|', $tmp);
		foreach($lignes as $numLgn => $detailsLigne) {
			$detailsLigne = str_replace(';;', '; ;', $detailsLigne);
			$colonnes = explode(';', trim($detailsLigne, ';'));
			$name = $colonnes[0];
			if ($numLgn == 0 || trim($detailsLigne, ';') == '') { continue; }
			// Gestion THEAD
			if ($numLgn == 1) {
				$tabHead = $colonnes; // Thead du fichier en cours d'import
				mg::messageT('', "Lecture de la nouvelle tête de $nomTab (".count($tabHead)." items) - Nombre de clefs '$nbKeys' " . print_r($tabHead, true));
				continue;
			}

		// -------------------------------- nbKeys == 1 ---------------------------------
		if ($nbKeys == 1) {
				foreach($colonnes as $numCol => $value) {
					$tabParams[$name][$tabHead[$numCol]] = trim($value);
				}
		// -------------------------------- nbKeys == 2 ---------------------------------
		} else {
			foreach($colonnes as $numCol => $value) {
					$section = $colonnes[0];
					$name = $colonnes[1];
				if ($numCol > 1) {
					// Gestion des lignes de rem
					if (trim($section) == '' || strpos($section, '_REM_') !== false || trim($colonnes[2]) == '') {
						$section = "section_REM_$numLgn";
						$name = "name_REM_$numLgn";
					}
					$tabParams[$section][$name][$tabHead[$numCol]] = trim($value);
				}
			}
		}
	}
	
	// Gestion de la table des tooltips
	addTabTooltips($nomTab, $tabHead, $tabTooltipsName, $tabTooltips);
	
	mg::messageT('', "!Transpo en BdD terminée");
	mg::setVar($nomTab, $tabParams);
	//mg::message('', print_r($tabParams, true));
}

// ************************************************ GENERATION DU HTML ************************************************
if ($submit == 'Charger' || $submit == 'Enregistrer' || $submit == 'Restaurer') {
	mg::messageT('', "Chargement de la table '$nomTab'.");

	// ***************************** Lancement de la régénération de la table '_alertes' ******************************
	if ($nomTab == '_alertes') {
		mg::setScenario($scenario_alertes, 'start', "nomTab=_alertes action=$submit");
		mg::wait("scenario($scenario_alertes) == 0", 180);
	}
	// ****************************************************************************************************************
	
	$tabParams = mg::getVar($nomTab);
	$tabHead = getKey($tabParams, $prefHidden, $nbKeys); 
	mg::messageT('', "Lecture de l'en tête de $nomTab (".count($tabHead)." items) - Nombre de clefs '$nbKeys' " . print_r($tabHead, true));

	// THEAD du tableau
	mg::messageT('', "Génération des lignes de 'Thead'");
	$boutonsDroiteGauche = "<button id='bt-left' class='bt-left' onClick='bt_left()'>&lsaquo;</button> <button id='bt-right' class='bt-right' onClick='bt_right()'>&rsaquo;</button>";
	$boutonsFonctionsColonnes = "<button id='bt-add' class='bt-add' onClick='bt_add()'>+</button> <button id='bt-del' class='bt-del' onClick='bt_del()'><img src=$imgPoubelle><span class='tooltiptext'></button>";

	$boutonsFonctionsLignes = "<th></th><th></th><th></th><th></th>";
	$lignesHead = '';
	$boutons = '';
	
	foreach ($tabHead as $num => $name) {
		// https://www.w3schools.com/howto/howto_css_tooltip.asp
		$txtTooltip = '';
		if (isset($tabTooltips[$nomTab][$name]['value'])) {
			$txtTooltip = $tabTooltips[$nomTab][$name]['value'];
		}
		$tooltip = "<br><div class='tooltip'><img src='$imgTooltip'><span class='tooltiptext'>$txtTooltip</span></div>";

		if ($nomTab[0] != '_') { 
			if ($num >= $nbKeys) { 
				$boutons = "<br>$boutonsDroiteGauche $boutonsFonctionsColonnes";
			} else {
				$boutons = "<br><br>";
			}
		}
		$lignesHead .= "<th>$name$tooltip$boutons</th>\n";
	}
	if ($nomTab[0] != '_') { $lignesHead .= $boutonsFonctionsLignes; }
	mg::message('', $lignesHead);

	// ****************************************************************************************************************
	// ********************************************** GENERATION DU TBODY *********************************************
	// ****************************************************************************************************************
	$out = teteTab($nomTab, $listTab, $apikey, $numScenario, $lignesHead, $minified);
	// -------------------------------- nbKeys == 1 - --------------------------------
	if ($nbKeys == 1) {
		// Lecture du fichier param et fabrication lignes du tableau
		mg::messageT('', "Génération des lignes de 'Tbody' de $nomTab - $nbKeys clef");
		foreach ($tabParams as $name => $detailsLigne) {
			// ligne rendu invisible (tx) si section vide pour ligne de remarque
			$name2 = (strpos($name, '_REM_') !== false ) ? "<tx>$name</tx>" : (trim($name) != '' ? mg::toHuman($name): ' ');
			if ($name2 != '') {
				$out .= "<tr><td>$name2</td>";
			}
			foreach ($tabHead as $num => $colonne) {
				if(substr($colonne, 0, 1) == $prefHidden) { continue; } // Masquage de la ligne à l'affichage si préfixé de '-'
				if ($colonne ==	'name') { continue; }
				$OK = 0;
				foreach ($detailsLigne as $nomCol => $value) {
						// Recalcul du zero pour l'affichage
						$value = trim($value);
						$value = strlen($value)==1 && $value=='0' ? '0.0' : $value;
					// Pose dans la bonne colonne
					if (trim($nomCol) == trim($colonne)) {
						$out .= '<td>'.(trim($value) != '' ? mg::toHuman($value) : ' ').'</td>';
						$OK = 1;
					}
				}
				if (!$OK && $nomTab[0] != '_') { $out .= '<td> </td>'; }
			}
				$out .= "</tr>\n";
		}
	// -------------------------------- nbKeys == 2 - --------------------------------
	} else {
		// Lecture du fichier param et fabrication lignes du tableau
		mg::messageT('', "Génération des lignes de 'Tbody' de $nomTab - $nbKeys clefs");
		foreach ($tabParams as $section => $detailsSection) {
			foreach ($detailsSection as $name => $detailsName) {
				$out .= '<tr>';
				// cellules rendues invisibles (tx) si section/name vide pour ligne de remarque
				$section2 = (strpos($section, '_REM_') !== false ) ? "<tx>$section</tx>" : (trim($section) != '' ? mg::toHuman($section) : ' ');
				$out .= "<td>$section2</td>";
				$name2 = (strpos($name, '_REM_') !== false ) ? "<tx>$name</tx>" : (trim($name) != '' ? mg::toHuman($name) : ' ');
				$out .= "<td>$name2</td>";

				foreach ($tabHead as $num => $colonne) {
					if (!is_array($detailsName)) { continue; }
					foreach ($detailsName as $nomCol => $value) {
						// Recalcul du zero pour l'affichage
						$value = $value;
						$value = strlen($value) == 1 && $value=='0' ? '0.0' : $value;
						$value = $value == '' || !$value ? ' ' : $value; 
						// Pose dans la bonne colonne
						if (trim($nomCol) == trim($colonne)) {
							// Gestion des REM
							if (strpos($section2, '_REM_') !== false && $nomCol == 'value') {
								$out .= '<td id=rem>'.(trim($value) != '' ? mg::toHuman($value) : ' ').'</td>';
							} elseif ($nomCol == 'value') {
								$out .= '<td>'.(trim($value) != '' ? mg::toHuman($value) : ' ').'</td>';
							} else {
								$out .= "<td> ".trim($value)."</td>";
							}
						}
					}
				} // tabHead
				$out .= "</tr>\n";
			}
		}
	}
}

	$out .= piedTab($nomTab, $listTab, $apikey, $numScenario, $boutonsDroiteGauche.$boutonsFonctionsColonnes, $minified, $nbKeys, $imgPoubelle);
	file_put_contents($FileExport, $out);
	//mg::message('', $out);
	
	// ********************** Signale à 'HtmlEditTable_POST.php' que le traitement est terminé ************************
	mg::setVar('_htmlOK', '1');
	
	// ****************************************************************************************************************

// ********************************************************************************************************************
// ************************************************** ADD TAB TOOLTIP *************************************************
// ********************************************************************************************************************

function addTabTooltips($nomTab, $tabHead, $tabTooltipsName, &$tabTooltips) {
	foreach($tabHead as $num => $nomTete) {
		if (!isset($tabTooltips[$nomTab][$nomTete]['value']) || trim($tabTooltips[$nomTab][$nomTete]['value']) == '' ) {
			$tabTooltips[$nomTab][$nomTete]['value'] = ' ';
			$tabTooltips[$nomTab][$nomTete]['remarque'] = ' ';
		}
	}
	mg::setVar($tabTooltipsName, $tabTooltips);
}

// ***************************************************** GET KEY ******************************************************
/* Renvoi un tableau des clefs du tableau, ajoute 'section,name,' au tableau à deux clefs							 */
/* le nb de clef du tableau est retourné dans la variable $nbKeys.													 */
// ********************************************************************************************************************
function getKey($tab, $prefHidden, &$nbKeys) {

	// Balayage de la table pour trouver TOUTES les colonnes
	foreach($tab as $clef1 => $tab1) {
		unset($tab2); unset($clef2);
		foreach($tab1 as $clef2 => $tab2) {
			$tab_2[$clef2] = ' ';
			if (!is_array($tab2)) { continue; }
			foreach($tab2 as $clef3 => $tab3) {
				$tab_3[$clef3] = ' ';
			}
		}
	}

	$result2 = tmp($tab_2, $prefHidden);
	if (isset($tab_3)) { $result3 = tmp($tab_3, $prefHidden); } else { $result3=''; }

	if($result3) {
		$nbKeys = 2;
		$tHead = 'section,name'.tmp($tab_3, $prefHidden);
	} else {
		$nbKeys = 1;
		$tHead = 'name'.tmp($tab_2, $prefHidden);
	}
	mg::message('', "Nb de clefs : $nbKeys	- THEAD : $tHead");
	return explode(',', $tHead);
}
function tmp($tabTmp, $prefHidden) {
	$result = '';
	foreach($tabTmp as $value => $x) {
		if(trim($value) && $value[0] != $prefHidden && $value != 'name') { 
			$result .= ",$value";
		}
	}
	return $result;
}

// ********************************************************************************************************************
// ******************************************** TETE SPECIFIQUE DU TABLEAU ********************************************
// ********************************************************************************************************************

function teteTab($nomTab, $listTab, $apikey, $numScenario, $lignesHead, $minified) {
	$IP = mg::getTag('IP');
	$date = date('G\ \h\ i\ \m\n\ s\ \s', time());
	
	$nbSelect = 1;//count($listTab);
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

$tmp = "
<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Strict//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'>
<html>
	<head>
	<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
	<style type='text/css'>
	  @import url(./HtmlEditTable$minified.css);
	</style>
  </head>
	<body>

<!-- Debut HtmlEditTable - $nomTab -->

		<center>
		<!-------------------------------------------------------------------------------------------------------->
		<div>
			<h2>(<span id='count'> <%= model.length %> style=color:'white'</span> lignes VISIBLES dans $nomTab ($date).
			<button value='Reload' style='background:red; color:white;verflow:auto;' id='reload'  onclick='reload();'>--- Reload ---</button>
		</div>
		<div>

		<div style='margin-left:-70%;'>
			<input type='text' value='' id='button_change' class='search' data-table='table' data-count='#count' placeholder='Saisissez ici du texte pour filtrer l`affichage'>
		</div>

		<div style='margin-left:35%; margin-top:-30px;display:block'>
			<!-- ///////////////////////////////////////////// -->
			<FORM method=post action='./HtmlEditTable_POST.php' enctype='application/x-www-form-urlencoded' onsubmit='return appelJeedom()'>
				<input	type='submit' class='bt-rest' name='submit' value='Restaurer'>

				<input	type='submit' class='bt-charge' name='submit' value='Charger'>
				<input type='hidden' id='message' name='message'>
				<input type='hidden' name='nomTabOrg' value=$tabOrg>
				<input type='hidden' name='IP' value=$IP>
				<input type='hidden' name='apikey' value=$apikey>
				<input type='hidden' name='numScenario' value=$numScenario>
				<SELECT name='nomTab' class='select' size='$nbSelect'>
					$select
				</SELECT>
				<input	type='submit' class='bt-sav' name='submit' value='Enregistrer'>
			</FORM>
			<!-- ///////////////////////////////////////////// -->
		</div>
		<br>
		<div id='wait'></div>
		<!-------------------------------------------------------------------------------------------------------->
			<table id='tableHTML'>
				<thead>
					<tr height=30px >
$lignesHead
					</tr>
				</thead>
				<tbody>
";
return $tmp;
}

// ********************************************************************************************************************
// ******************************************** PIED SPECIFIQUE DU TABLEAU ********************************************
// ********************************************************************************************************************

function piedTab($nomTab, $listTab, $apikey, $numScenario, $boutonsFonctionsColonnes, $minified, $nbKeys, $imgPoubelle) {
	$imgWait = 'wait.gif';
	$IP = mg::getTag('IP');
	
	// Génération du select
	$nbSelect = 1;//count($listTab);
	$select = '';
	//$select .= "<OPTION>Sélectionner la table à utiliser</option>\n";
	for($i=0; $i<count($listTab);$i++) {
		$tab = $listTab[$i];
		if (trim($nomTab) != trim($tab)) {
			$select .= "<option value='$tab'>$tab</option>\n";
		} else {
			$select .= "<option selected value='$tab'>$tab</option>\n";
			$tabOrg = $tab;
		}
	}

	// --------------------------------------------------------------------------------
	$tmp = "
				</tbody>
			</table>
	<!---------------------------------------------------------------------------------------------------------------->
			<br/>
		<div style='left'>

		<div style='margin-left:-70%;'>
			<input type='text' value='' id='button_change2' class='search' data-table='table' data-count='#count' placeholder='Saisissez ici du texte pour filtrer l`affichage'>
		</div>

		<div style='margin-left:35%; margin-top:-30px;display:block'>
			<!-- ///////////////////////////////////////////// -->
			<FORM method=post action='./HtmlEditTable_POST.php' enctype='application/x-www-form-urlencoded' onsubmit='return appelJeedom()'>
				<input	type='submit' class='bt-rest' name='submit' value='Restaurer'>

				<input	type='submit' class='bt-charge' name='submit' value='Charger'>
				<input type='hidden' id='message2' name='message2'>
				<input type='hidden' name='nomTabOrg' value=$tabOrg>
				<input type='hidden' name='IP' value=$IP>
				<input type='hidden' name='apikey' value=$apikey>
				<input type='hidden' name='numScenario' value=$numScenario>
				<SELECT name='nomTab' class='select' size='$nbSelect'>
					$select
				</SELECT>
				<input	type='submit' class='bt-sav' name='submit' value='Enregistrer'>
			</FORM>
			<!-- ///////////////////////////////////////////// -->
		</div>

	<script>
		// Variables JS à déclarer avant le code JS
		var boutonFonctionsColonnes = \"$boutonsFonctionsColonnes'\";
		var nomTab = '$nomTab';
		var nbKeys = $nbKeys;
		var imgPoubelle = '$imgPoubelle';
	</script>

	<script src='https://code.jquery.com/jquery-3.4.1.min.js'></script>
	<script type='text/javascript' src='./HtmlEditTable$minified.js'></script>


	<script type='text/javascript'>
		// ------------------------------------------------ INITIALISATION ------------------------------------------------
		var tableHTML = document.getElementById('tableHTML');
		var tableEdit = new HtmlEditTable({'table': tableHTML});

//			setTimeout(document.location.reload(), 5000);
		function reload() { document.location.reload(); }
		
		appelJeedom = function() {
			// Affichage image d'attente
			var nouvelleimage=document.createElement('IMG');
			document.getElementById('wait').appendChild(nouvelleimage);
			document.getElementById('wait').lastChild.setAttribute('src','$imgWait');			
			
			allData = tableEdit.AllData().join(';');
//			alert(nombreItem() + ' items' + allData);
			if (allData.length < 10) { 
				alert ('Tableau < 10 octets, Annulation de la sauvegarde !!!')
				return;
			}
			document.getElementById('message').value=allData;
			document.getElementById('message2').value=allData;
			return true;
		}
	</script>

		</center>
<!-- FIN HtmlEditTable - $nomTab -->
	</body>
</html>
";
return $tmp;
}

?>