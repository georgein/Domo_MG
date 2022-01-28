<?php
/**********************************************************************************************************************
Geo_Synthese - 202

**********************************************************************************************************************/
global $tabActivite, $tableau, $tableau0, $color, $cptLgn, $idUser, $dateOrg, $dateOrgTxt, $IP_Jeedom;

// Infos, Commandes et Equipements =>

// N° des scénarios =>

// Variables =>
	$tabActivite = '_tabActivites';
	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG

// Paramètres =>
	$IP_Jeedom = mg::getConfigJeedom('core', 'jeedom::url');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

synthese('MG', $pathRef);
synthese('NR', $pathRef);

/********************************************************************************************************************/
/*********************************************** EDITION SYNTHES $USER **********************************************/
/********************************************************************************************************************/
function synthese($user, $pathRef) {
global $tableau, $tableau0, $color, $cptLgn, $idUser, $dateOrg, $dateOrgTxt, $IP_Jeedom;
	$fileSynthese = getRootPath() . "$pathRef/util/synthese_$user.html";

	// Calcul de la date minimum entre les Activité et le poids
	$result_E = getActivites($user, 0, time(), 1);

	$idUser = trim(mg::toID("[Sys_Présence][Balance $user]", 'Poids'), '#');
	$result_P = getPoids($idUser, 0, time(), 1);

	$dateOrg = strtotime(min($result_E[0]['datetime'], $result_P[0]['datetime']));
	$dateOrgTxt = date('d\/m\/Y', $dateOrg);

	/*****************************************************************************************************************/
	$style = "
		<style type='text/css'>
			html { font-family:Calibri, Arial, Helvetica, sans-serif; font-size:11pt; background-color:white }
			a.comment-indicator:hover + div.comment { background:#ffd; position:absolute; display:block; border:1px solid black; padding:0.5em }
			a.comment-indicator { background:red; display:inline-block; border:1px solid black; width:0.5em; height:0.5em }

			div.comment { display:none }
			table { border-collapse:collapse; page-break-after:always }
			.gridlines td { border:1px dotted black }
			.gridlines th { border:1px dotted black }

			.r { text-align:right!important }
			.c { text-align:center!important }
			.l { text-align:left!important }

			.colorP { background-color:orange!important }
			.colorM { background-color:lightblue!important }

			.c_Somme { background-color:orange!important }
			.c_vm { background-color:orange!important }
			.c_IBP { background-color:red!important }

			td.titre { vertical-align:bottom; text-align:center; border-bottom:3px solid #000000 !important; border-top:3px solid #000000 !important; border-left:3px solid #000000 !important; border-right:3px solid #000000 !important; font-weight:bold; color:#FFFFFF; font-family:'Calibri'; font-size:18pt; background-color:#FF6600 }

			td.cellMoy { width:100px; vertical-align:bottom; text-align:center; border-bottom:2px solid #000000 !important; border-top:3px solid #000000 !important; border-left:2px solid #000000 !important; border-right:2px solid #000000 !important; font-weight:bold; color:#000000; font-family:'Calibri'; font-size:11pt; background-color:white }

			td.cellJour { width:100px; vertical-align:bottom; text-align:center; border-bottom:2px solid #000000 !important; border-top:3px solid #000000 !important; border-left:2px solid #000000 !important; border-right:2px solid #000000 !important; font-weight:bold; color:#000000; font-family:'Calibri'; font-size:11pt; background-color:#e8b8b9 }

			td.titreLigne { width:175px; vertical-align:bottom; text-align:center; border-bottom:2px solid #000000 !important; border-top:3px solid #000000 !important; border-left:3px solid #000000 !important; border-right:2px solid #000000 !important; font-weight:bold; color:#000000; font-family:'Calibri'; font-size:11pt; background-color:#FFFF99 }

			td.lgnVide { height:10px; vertical-align:bottom; text-align:center; border-bottom:3px solid #000000 !important; border-top:3px solid #000000 !important; border-left:3px solid #000000 !important; border-right:3px solid  #000000; font-weight:bold; color:#000000; font-family:'Calibri'; font-size:11pt; background-color:white }


			td.style10 { vertical-align:bottom; text-align:center; border-bottom:3px solid #000000 !important; border-top:3px solid #000000 !important; border-left:2px solid #000000 !important; border-right:2px solid #000000 !important; font-weight:bold; color:#FFFFFF; font-family:'Calibri'; font-size:11pt; background-color:#99CC00 }

			td.style11 { vertical-align:bottom; text-align:center; border-bottom:3px solid #000000 !important; border-top:3px solid #000000 !important; border-left:2px solid #000000 !important; border-right:none #000000; font-weight:bold; color:#FFFFFF; font-family:'Calibri'; font-size:11pt; background-color:#99CC00 }

			td.style12 { vertical-align:bottom; text-align:center; border-bottom:3px solid #000000 !important; border-top:3px solid #000000 !important; border-left:none #000000; border-right:2px solid #000000 !important; font-weight:bold; color:#FFFFFF; font-family:'Calibri'; font-size:11pt; background-color:#99CC00 }

			td.style13 { vertical-align:bottom; text-align:center; border-bottom:3px solid #000000 !important; border-top:3px solid #000000 !important; border-left:2px solid #000000 !important; border-right:none #000000; font-weight:bold; color:#FFFFFF; font-family:'Calibri'; font-size:11pt; background-color:#99CC00 }

			td.style14 { vertical-align:bottom; text-align:center; border-bottom:3px solid #000000 !important; border-top:3px solid #000000 !important; border-left:none #000000; border-right:2px solid #000000 !important; font-weight:bold; color:#FFFFFF; font-family:'Calibri'; font-size:11pt; background-color:#99CC00 }

			td.style15 { vertical-align:bottom; text-align:center; border-bottom:none #000000; border-top:3px solid #000000 !important; border-left:3px solid #000000 !important; border-right:3px solid #000000 !important; font-weight:bold; color:#000000; font-family:'Calibri'; font-size:11pt; background-color:#FFFF99 }

			td.style22 {vertical-align:bottom; border-bottom:none #000000; border-top:3px solid #000000 !important; border-left:3px solid #000000 !important; border-right:3px solid #000000 !important; color:#000000; font-family:'Calibri'; font-size:11pt; background-color:white }

			td.style23 { vertical-align:bottom; border-bottom:2px solid #000000 !important; border-top:none #000000; border-left:3px solid #000000 !important; border-right:3px solid #000000 !important; color:#000000; font-family:'Calibri'; font-size:11pt; background-color:white }

			.barre_button {
/*			text-align: end;
				z-index: 9999!important;
				position: relative;  */
				position: absolute;
				/* display: contents; */
				height: 1px;
				 margin-left:30%;
				 margin-right:20%;
				     margin-top: -50px;
			}

			.button {
				 background-color:grey;
				 border: none;
				 color: white;
				 text-align: center;
				 text-decoration: none;
				 display: inline-block;
				 font-size: 30px;
				 padding: 8px 15px 8px 15px;
				 margin: 4px 2px;
				 cursor: pointer;
			}

	</style>
	";

	// ***************************************************** TABLEAU ******************************************************

	$tete = "
	<!DOCTYPE html PUBLIC '-//W3C//DTD HTML 4.01//EN' 'http://www.w3.org/TR/html4/strict.dtd'>
	<html>
	  <head>
		  <meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
	</head>
	";

	$buttons = "
		<div class='barre_button'>
		  <a href='$IP_Jeedom/mg/util/geofence.html' class='button'>Geofence</a>
		  <a href='$IP_Jeedom/mg/util/synthese_MG.html' class='button'>Synthese MG</a>
		  <a href='$IP_Jeedom/mg/util/synthese_NR.html' class='button'>Synthese NR</a>
		  <a href='$IP_Jeedom/mg/tabulator/tabulator.html' class='button'>Historique</a>
		</div>
		<br>";

//	$tete Tableau
	$tableau = "
		<body>
		<style>
			@page { margin-left: 10px; margin-right: 10px; margin-top: 10px; margin-bottom: 10px; }
			body { margin-left: 10px; margin-right: 10px; margin-top: 10px; margin-bottom: 10px;padding-top:50px; }
		</style>
			<table border='0' cellpadding='0' cellspacing='0' id='sheet0' class='sheet0 gridlines'>
				<tbody>
	";

	// Tableau de tête
	$tableau0 = $tableau;
	$tableau0 .= titre("SYNTHESE STATISTIQUES de $user depuis le $dateOrgTxt.");
	$tableau0 .= ligneSousTitre1();
	$tableau0 .= ligneSousTitre2();


	// Tableau principal
	$cptLgn = 0;
	$tableau .= titre("Stat. Quotidiennes sur les 7 derniers jours de $user.");
	$tableau .= ligneSousTitre1();
	$tableau .= ligneSousTitre2();
	$tableau .= quotidienne($user); // 7 jours
	$tableau .= ligneVide();

	$tableau .= titre("Stat. Hebdomadaires sur les 4 dernières semaines de $user.");
	$tableau .= ligneSousTitre1();
	$tableau .= ligneSousTitre2();
	$tableau .= hebdo($user); // 4 semaines
	$tableau .= ligneVide();

	$tableau .= titre("Stat. Mensuelles sur les 12 derniers mois  de $user.");
	$tableau .= ligneSousTitre1();
	$tableau .= ligneSousTitre2();
	$tableau .= mensuel($user); // 12 mois
	$tableau .= ligneVide();

	$tableau .= titre("Stat. annuelle depuis le $dateOrgTxt de $user.");
	$tableau .= ligneSousTitre1();
	$tableau .= ligneSousTitre2();
	$tableau .= annuel($user); // x année
	$tableau .= ligneVide();

	$tableau .= titre("Stat. Générales du $dateOrgTxt au ".date('d\/m\/Y \à H\hi\m\n', time())." de $user.");
	$tableau .= ligneSousTitre1();
	$tableau .= ligneSousTitre2();
	$tableau .= ALL($user);
	$tableau .= ligneVide();

	$tableau .= "
			</tbody>
		</table>
	";

	// ********** Sortie finale **********
	$HTML = '';
	$HTML .= $tete;
	$HTML .= $style;
	$HTML .= $buttons;
	$HTML .= "<div>$tableau0</div>";
	$HTML .= ligneVide('40px');
	$HTML .= "<div>$tableau</div> </body></html>";

	mg::message('', $HTML);
	file_put_contents($fileSynthese, $HTML);

}

/*************************************************************************************************************************/
/*************************************************************************************************************************/
/*************************************************************************************************************************/
function titre($titre) {
	global $cptLgn;
	$cptLgn++;
	return "
	<tr class='row0'>
		<td class='titre c' colspan='21'>$titre</td>
	</tr>
	";
}

function ligneSousTitre1() {
	global $cptLgn;
	$cptLgn++;
	return "
	<tr class=\"row$cptLgn\">
		<td class='style22 null'></td>
		<td class='style10 s'>Poids</td>
		<td class='style10 s'>Nb</td>
		<td class='style11 s style11' colspan='2'>IBP</td>
		<td class='style11 s style11' colspan='2'>Km</td>
		<td class='style11 s style11' colspan='2'>Km Effort</td>
		<td class='style11 s style11' colspan='2'>Deniv +</td>
		<td class='style11 s style11' colspan='2'>Deniv -</td>
		<td class='style10'>Durée Mvmt</td>
		<td class='style10'>Durée Pause</td>
		<td class='style10''>Durée Glob.</td>
		<td class='style10 s'>Vit. Mvm.</td>
		<td class='style10 s'>Vit. Glob.</td>
		<td class='style11 s style11' colspan='2'>Km Voit.</td>
		<td class='style22 null'></td>
	</tr>
	";
}

function ligneSousTitre2() {
	global $cptLgn;
	$cptLgn++;
	return "
	<tr class=\"row$cptLgn\">
		<td class='style23 null'></td>
		<td class='style15 s'>Somme</td>
		<td class='style15 s'>Act./Tot.</td>
		<td class='style15 s'>Somme</td>
		<td class='style15 s'>M./Jour</td>
		<td class='style15 s'>Somme</td>
		<td class='style15 s'>M./Jour</td>
		<td class='style15 s'>Somme</td>
		<td class='style15 s'>M./Jour</td>
		<td class='style15 s'>Somme</td>
		<td class='style15 s'>M./Jour</td>
		<td class='style15 s'>Somme</td>
		<td class='style15 s'>M./Jour</td>
		<td class='style15 s'>M./Jour</td>
		<td class='style15 s'>M./Jour</td>
		<td class='style15 s'>M./Jour</td>
		<td class='style15 s'>Moy.</td>
		<td class='style15 s'>Moy.</td>
		<td class='style15 s'>Somme</td>
		<td class='style15 s'>M./Jour</td>
		<td class='style23 null'></td>
	</tr>
	";
}

function ligneVide($height='10px') {
	global $cptLgn;
	$cptLgn++;
	return "
	<tr class=\"row$cptLgn\">
		<td class='lgnVide' style='height:$height;' colspan='21'></td>
	</tr>
	";
}

/*********************************************************************************************************************/
/************************************************** LIGNE AFFICHAGE **************************************************/
/*********************************************************************************************************************/
function ligne($aff) {
	global $color, $cptLgn;
	$null = '-';

	if ($aff['s_IBP'] > 0) {
		return "
		<tr class=\"row$cptLgn\">
			<td class='titreLigne $color l'>{$aff['titreLigne']}</td>
			<td class='cellMoy c'>".($aff['s_poids'] > 0 ? $aff['j_poids'] : $null)."</td>
			<td class='cellJour s'>{$aff['nb']}</td>
			<td class='cellMoy c'>{$aff['s_IBP']}</td>
			<td class='cellJour c_IBP'>{$aff['j_IBP']}</td>
			<td class='cellMoy c_Somme'>{$aff['s_km_E']}</td>
			<td class='cellJour c'>{$aff['j_km_E']}</td>
			<td class='cellMoy c_Somme'>{$aff['s_km_Effort']}</td>
			<td class='cellJour c'>{$aff['j_km_Effort']}</td>
			<td class='cellMoy c'>{$aff['s_deniv_P']}</td>
			<td class='cellJour c'>{$aff['j_deniv_P']}</td>
			<td class='cellMoy c'>{$aff['s_deniv_M']}</td>
			<td class='cellJour c'>{$aff['j_deniv_M']}</td>
			<td class='cellJour c'>". date('H:i:s', strtotime(date('Y-m-d 00:00:00'). '+' .round($aff['j_duree_Mvmt']). ' sec'))."</td>
			<td class='cellJour c'>". date('H:i:s', strtotime(date('Y-m-d 00:00:00'). '+' .round($aff['j_duree_Pause']). ' sec'))."</td>
			<td class='cellJour c'>".date('H:i:s', $aff['j_duree_Glob'])."</td>
			<td class='cellJour c_vm'>{$aff['j_vitesse_Mvmt']}</td>
			<td class='cellJour c'>{$aff['j_vitesse_Glob']}</td>
			<td class='cellMoy c'>".($aff['s_km_V'] > 0 ? $aff['s_km_V'] : $null)."</td>
			<td class='cellJour c'>".($aff['s_km_V'] > 0 ? $aff['j_km_V'] : $null)."</td>
			<td class='titreLigne $color r'>{$aff['titreLigne']}</td>
		</tr>";
	} else  {
		return "
		<tr class=\"row$cptLgn\">
			<td class='titreLigne $color l'>{$aff['titreLigne']}</td>
			<td class='cellMoy c'>".($aff['s_poids'] > 0 ? $aff['j_poids'] : $null)."</td>
			<td class='cellJour c'>{$aff['nb']}</td>
			<td class='cellMoy c'>$null</td>
			<td class='cellJour c'>$null</td>
			<td class='cellMoy c'>$null</td>
			<td class='cellJour c'>$null</td>
			<td class='cellMoy c'>$null</td>
			<td class='cellJour c'>$null</td>
			<td class='cellMoy c'>$null</td>
			<td class='cellJour c'>$null</td>
			<td class='cellMoy c'>$null</td>
			<td class='cellJour c'>$null</td>
			<td class='cellJour c'>$null</td>
			<td class='cellJour c'>$null</td>
			<td class='cellJour c'>$null</td>
			<td class='cellJour c'>$null</td>
			<td class='cellJour c'>$null</td>
			<td class='cellMoy c'>".($aff['s_km_V'] > 0 ? $aff['s_km_V'] : $null)."</td>
			<td class='cellJour c'>".($aff['s_km_V'] > 0 ? $aff['j_km_V'] : $null)."</td>
			<td class='titreLigne $color r'>{$aff['titreLigne']}</td>
		</tr>";
	}
}

/*********************************************************************************************************************/
/************************************************ CALCUL QUOTIDIENNE *************************************************/
/*********************************************************************************************************************/
function quotidienne($user) {
	global $tableau, $tableau0, $cptLgn, $color;
	$affLgn = '';

	// 7 derniers jours
	$dateMin = strtotime("today");
	for ($i=0; $i<7; $i++) {
		$cptLgn++;
//		$dateMax = strtotime("- 7 day", $dateMin);
		$dateMax = strtotime("today", $dateMin);

		$result = getActivites($user, $dateMin, $dateMax);
		$aff = valLigne($result, $dateMin, $dateMax);
		$affLgn .= ligne($aff);
//		$dateMin = strtotime("last Monday", $dateMin-1*1440*60);
		$dateMin = strtotime("- 1 day", $dateMin);
	}
	return $affLgn;
}

/*********************************************************************************************************************/
/*************************************************** CALCUL HEBDO ****************************************************/
/*********************************************************************************************************************/
function hebdo($user) {
	global $tableau, $tableau0, $cptLgn, $color;
	$affLgn = '';

	// Journée courante
	$cptLgn++;
	$dateMax = strtotime("today");
	$dateMin = strtotime("today");
	$result = getActivites($user, $dateMin, $dateMax);
	$aff = valLigne($result, $dateMin, $dateMax);
		$aff['titreLigne'] = 'Journée courante';
	$color = 'colorP';
	$tableau0 .= ligne($aff);

	// Semaine glissante
	$cptLgn++;
	$dateMax = strtotime("today");
	$dateMin = strtotime("- 6 day", $dateMax);
	$result = getActivites($user, $dateMin, $dateMax);
	$aff = valLigne($result, $dateMin, $dateMax);
	$aff['titreLigne'] = 'Semaine glissante';
	$color = 'colorP';
	$tableau0 .= ligne($aff);

	// 4 dernières semaines
	$dateMin = strtotime("last Monday");
	for ($i=0; $i<4; $i++) {
		$cptLgn++;
		$dateMax = strtotime("next Sunday", $dateMin);

		$result = getActivites($user, $dateMin, $dateMax);
		$aff = valLigne($result, $dateMin, $dateMax);
		$affLgn .= ligne($aff);

		$dateMin = strtotime("last Monday", $dateMin-6*1440*60);
	}
	return $affLgn;
}

/*********************************************************************************************************************/
/************************************************** CALCUL MENSUEL ***************************************************/
/*********************************************************************************************************************/
function mensuel($user) {
	global $tableau, $tableau0, $cptLgn, $color;
	$affLgn = '';

	// Mois glissant
	$dateMax = strtotime("today");
	$dateMin = strtotime("- 1 month", $dateMax + 1440*60);
	$result = getActivites($user, $dateMin, $dateMax);
	$aff = valLigne($result, $dateMin, $dateMax);
	$aff['titreLigne'] = 'Mois glissant';
	$color = 'colorP';
	$tableau0 .= ligne($aff);

	// 12 derniers mois
	$dateMin = strtotime("first day of this month");
	for ($i=0; $i<12; $i++) {
		$cptLgn++;
		$dateMax = strtotime("last day of this month", $dateMin);

		$result = getActivites($user, $dateMin, $dateMax);
		$aff = valLigne($result, $dateMin, $dateMax);
		$affLgn .= ligne($aff);

		$dateMin = strtotime("first day of last month", $dateMin);
	}
	return $affLgn;
}

/*********************************************************************************************************************/
/*************************************************** CALCUL ANNUEL ***************************************************/
/*********************************************************************************************************************/
function annuel($user) {
	global $tableau, $tableau0, $cptLgn, $dateOrg, $color;
	$affLgn = '';

	// Année glissante
	$dateMax = strtotime("today");
	$dateMin = strtotime("- 1 year", $dateMax + 1440*60);
	$result = getActivites($user, $dateMin, $dateMax);
	$aff = valLigne($result, $dateMin, $dateMax);
	$aff['titreLigne'] = 'Année glissante';
	$color = 'colorP';
	$tableau0 .= ligne($aff);

	// x dernières années
	$dateMin = strtotime("first day of january");
	for ($i=0; $i<99; $i++) {
		$cptLgn++;
		$dateMax = strtotime("last day of december", $dateMin);
		if ($dateOrg > $dateMax) break;

		$result = getActivites($user, $dateMin, $dateMax);
		$aff = valLigne($result, $dateMin, $dateMax);
		$affLgn .= ligne($aff);

		$dateMin = strtotime("first day of last year", $dateMin);
	}
	return $affLgn;
}

/*********************************************************************************************************************/
/************************************************ CALCUL ALL PERIODES ************************************************/
/*********************************************************************************************************************/
function ALL($user) {
	global $tableau0, $cptLgn, $idUser, $dateOrg, $dateOrgTxt, $color;
	$result = getActivites($user, $dateOrg, time());
	$aff = valLigne($result, $dateOrg, time());

	$aff['titreLigne'] = "Depuis le $dateOrgTxt";
	$color = 'colorP';
	$tableau0 .= ligne($aff);

	$cptLgn++;
	$affLgn = ligne($aff);
	return $affLgn;
}

/*********************************************************************************************************************/
/************************************************ CALCUL VALEURS LIGNES **********************************************/
/*********************************************************************************************************************/
function valLigne($result, $dateMin, $dateMax) {
	global $idUser, $color;
	$color = '';

	$nbTotalJour = /*ceil*/round(($dateMax - $dateMin) / 1440 / 60)+1;

	$nbVal_E = 0; $nbVal_V = 0; $nbVal_P = 0;

	$aff['s_poids'] = 0;
	$aff['s_IBP'] = 0;
	$aff['s_km_E'] = 0;
	$aff['s_km_Effort'] = 0;
	$aff['s_deniv_P'] = 0;
	$aff['s_deniv_M'] = 0;
	$aff['s_duree_Mvmt'] = 0;
	$aff['s_duree_Pause'] = 0;
	$aff['s_duree_Glob'] = 0;
	$aff['s_km_V'] = 0;

	$aff['j_IBP'] = 0;
	$aff['j_km_E'] = 0;
	$aff['j_km_Effort'] = 0;
	$aff['j_deniv_P'] = 0;
	$aff['j_deniv_M'] = 0;
	$aff['j_duree_Mvmt'] = 0;
	$aff['j_duree_Pause'] = 0;
	$aff['j_duree_Glob'] = 0;
	$aff['j_vitesse_Mvmt'] = 0;
	$aff['j_vitesse_Glob'] = 0;
	$aff['j_km_V'] = 0;

	$aff['titreLigne'] = date('d\/m', $dateMin).'-'.date('d\/m\ Y', $dateMax);

	// Gestion du poids
	$result_P = getPoids($idUser, $dateMin, $dateMax);
	for ($i=0; $i<=count($result_P); $i++) {
		if (!isset($result_P[$i]['value']) || $result_P[$i]['value'] <=0) continue;
		$aff['s_poids'] += $result_P[$i]['value'];
		$nbVal_P++;
	}
	$aff['j_poids'] = ($nbVal_P > 0 ? round($aff['s_poids'] / $nbVal_P, 1).' kg' : 0);

	for ($i=0; $i<=count($result); $i++) {
		// Gestion Entrainements
		if (isset($result[$i]['km_E']) && $result[$i]['km_E'] > 0) $nbVal_E++;

		if ($nbVal_E > 0) {
			// Calcul TOTALISATIONS
			if (isset($result[$i]['IBP'])) $aff['s_IBP'] += $result[$i]['IBP'];
			if (isset($result[$i]['km_E'])) $aff['s_km_E'] += $result[$i]['km_E'];
			if (isset($result[$i]['km_Effort'])) $aff['s_km_Effort'] += $result[$i]['km_Effort'];
			if (isset($result[$i]['deniv_P'])) $aff['s_deniv_P'] += $result[$i]['deniv_P'];
			if (isset($result[$i]['deniv_M'])) $aff['s_deniv_M'] += $result[$i]['deniv_M'];
			if (isset($result[$i]['duree_Mvmt'])) $aff['s_duree_Mvmt'] += TimeToSec($result[$i]['duree_Mvmt']);
			if (isset($result[$i]['duree_Pause'])) $aff['s_duree_Pause'] += TimeToSec($result[$i]['duree_Pause']);
			if (isset($result[$i]['duree_Glob'])) $aff['s_duree_Glob'] += TimeToSec($result[$i]['duree_Glob']);

			// Affichage des MOYENNE JOURS ACTIFS
			$aff['j_IBP'] = round($aff['s_IBP'] / $nbVal_E);
			$aff['j_km_E'] = round($aff['s_km_E'] / $nbVal_E, 1).' km';
			$aff['j_km_Effort'] = round($aff['s_km_Effort'] / $nbVal_E, 1).' km';
			$aff['j_deniv_P'] = round($aff['s_deniv_P'] / $nbVal_E).' m';
			$aff['j_deniv_M'] = -round($aff['s_deniv_M'] / $nbVal_E).' m';

			$aff['j_duree_Mvmt'] = $aff['s_duree_Mvmt'] / $nbVal_E;
			$aff['j_duree_Pause'] = $aff['s_duree_Pause'] / $nbVal_E; 
			$aff['j_duree_Glob'] = $aff['s_duree_Glob'] / $nbVal_E;

			$tmp = '';
//			$tmp = ($aff['j_duree_Mvmt'] > 0 ? round($aff['s_km_Effort'] / $nbVal_E / $aff['j_duree_Mvmt']*3600, 1).' km/h' : 0);
			$aff['j_vitesse_Mvmt'] = ($aff['j_duree_Mvmt'] > 0 ? round($aff['s_km_E'] / $nbVal_E / $aff['j_duree_Mvmt']*3600, 1)."/$tmp km/h" : 0);

//			$tmp = ($aff['j_duree_Glob'] > 0 ? round($aff['s_km_Effort'] / $nbVal_E / $aff['j_duree_Glob']*3600, 1).' km/h' : 0);
			$aff['j_vitesse_Glob'] = ($aff['j_duree_Glob'] > 0 ? round($aff['s_km_E'] / $nbVal_E / $aff['j_duree_Glob']*3600, 1)."/$tmp km/h" : 0);
		} 

		// Gestion Voiture
		if (isset($result[$i]['km_V']) && $result[$i]['km_V'] > 0) $nbVal_V++;
		if ($nbVal_V > 0) {
			if (isset($result[$i]['km_V'])) $aff['s_km_V'] += $result[$i]['km_V'];
			$aff['j_km_V'] = round($aff['s_km_V'] / $nbVal_V).' km';
		}
	}

	$aff['nb'] = "$nbVal_E/$nbTotalJour";

	return $aff;
}

/*********************************************************************************************************************/
/************************************************* TIME TO SECONDES **************************************************/
/*********************************************************************************************************************/
function TimeToSec($time) {
	$times = explode(':', $time);
	$sec = $times[0]*3600 + $times[1]*60 + $times[2];
	return $sec;
}


/*********************************************************************************************************************/
/************************************ LIT LES DONNEES ACTIVITES ENTRE DEUX DATES *************************************/
/*********************************************************************************************************************/
function getActivites($user, $dateMin, $dateMax, $limit=10000, $sens='ASC') {
	global $tabActivite;
	$values = array();
	$dateMin = date('Y\-m\-d', $dateMin);
	$dateMax = date('Y\-m\-d', $dateMax);

	$sql = "SELECT *
		FROM `$tabActivite`
		WHERE `user` = '$user' AND `datetime` >= '$dateMin' AND `datetime` <= '$dateMax' AND (`km_E` > 0 OR `km_V` > 0)
		ORDER BY `datetime` $sens LIMIT $limit";

	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	return $result;
}

/*********************************************************************************************************************/
/************************************ LIT LES DONNEES DE POIDS ENTRE DEUX DATES *************************************/
/*********************************************************************************************************************/
function getPoids($idUser, $dateMin, $dateMax, $limit=10000, $sens='ASC') {
	$values = array();
	$dateMin = date('Y\-m\-d', $dateMin);
	$dateMax = date('Y\-m\-d', $dateMax);

	$sql = "
	SELECT *
		FROM (
			SELECT *
				FROM history
				WHERE cmd_id = $idUser AND `datetime` >= '$dateMin' AND `datetime` <= '$dateMax 23:59:59' AND `value` > 0
			UNION ALL
				SELECT *
					FROM historyArch
					WHERE cmd_id = $idUser AND `datetime` >= '$dateMin' AND `datetime` <= '$dateMax 23:59:59' AND `value` > 0
		) as dt
		ORDER BY `datetime` $sens LIMIT $limit";
	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	return $result;
}

?>