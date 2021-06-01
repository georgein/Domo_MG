<?php
/**********************************************************************************************************************
EDF_Tab_Conso - 127
Calcul les consommations par mois, années et totale.
La première ligne du tableau @TabConso doit OBLIGATOIREMENT commencer par le cpt_Ligne général et se terminer par ENEDIS EN DERNIER.
***********************************************************************************************************************/
global $tabConso, $tabNomCol, $tabAff, $cmdMaj_Aff;

// Infos, Commandes et Equipements :
	// $equipEDF, $equipConso

// N° des scénarios : 

//Variables :
	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
	$fileExportHTML = (getRootPath() . "$pathRef/util/conso_EDF.html"); 
	$fileExportJS = (getRootPath() . "$pathRef/util/conso_EDF.js"); 
	$width = 75;								// Largeur des colonnes numérique du tableau final, en px
	$periode = 60;								// (minute % periode !=0) ou le traitement ne sera pas partiel (doit être un multiple du cron).
	$detail_Lignes = mg::getCmd($equipConso, 'Detail_Lignes'); // Option Détaillé (1) ou Groupé (2), MàJ (3)

// Paramètres :
	$tabConso = (array)mg::getVar('tabConso');
	$consoCoutKWH = mg::getParam('EDF', 'consoCoutKWH');

/**********************************************************************************************************************
**********************************************************************************************************************/
$traitementPartiel = false;
$cmdMaj_Aff = trim(mg::toID($equipEDF, 'Maj_Aff_'), '#'); // Pour relnce via JS

// Gestion MàJ du widget (action à "JAMAIS REPETER")
if (mg::declencheur('Maj_Aff') && mg::getCmd($equipEDF, 'Maj_Aff')) {
	mg::messageT('', "! MàJ de l'affichage");
	mg::setInf($equipEDF, 'Maj_Aff', 0);
	goto maj;
//	return;
}
mg::setInf($equipEDF, 'Maj_Aff', 0);

// Traitement normal
if ($detail_Lignes == 3 && mg::getTag('#minute# ') % $periode != 0) { $traitementPartiel = true; }
elseif (mg::declencheur('schedule')) { $traitementPartiel = false; }
maj:
if ($traitementPartiel) {
	$tabAff = mg::getVar('_consoEDF_TabAff');
	$tabNomCol = mg::getVar('_consoEDF_TabNomCol');
	$nbLgnAffichage = mg::getVar('_consoEDF_NbLgn');
	mg::message('', "============> TRAITEMENT PARTIEL <=============================");
	goto Affichage;
}

// ============================================ Init du tableau d'affichage ===========================================
$tabNomCol = array('Nom', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12,'Mois courant', '% mois', 'Kw Mens.', 'Kw Ann.',  'Cout Ann.', '% Mens.', 'Puiss.', 'EquiConso', 'EquiPuis', 'EquiEtat', 'EquiAction');
$tabAff = array_fill(0, count($tabConso)+2, array_fill_keys($tabNomCol, ''));

// ================================ Boucle de lecture des équipements des 12 derniers mois ============================

$cpt_Mois = 1;
$annee = date("Y")-1;
$mois = (int)date('m');

while ($cpt_Mois <= 12) {
	ExtraitConsoMois($mois, $annee, $detail_Lignes, $cpt_Mois, $nbLgnAffichage);
	$mois++;
	if ($mois > 12) {
		$mois = 1;
		$annee = date("Y");
	}
	$cpt_Mois++;
}

// Mois courant
$annee = date("Y");
$mois = date('m');
ExtraitConsoMois($mois, $annee, $detail_Lignes, $cpt_Mois, $nbLgnAffichage, true);

// =============================== Boucle équipements / Mois pour calcul de totalisation ==============================
Totalisation ($nbLgnAffichage);

/* ====================================================================================================================
//										Fabrication du tableau à afficher
// ==================================================================================================================*/
Affichage:
// Raffraichissement de la puissance et 'total Incertitude'
$idxAutre = $nbLgnAffichage+1;
$numLigne = 0;
$oldType = '';

$tabAff[$idxAutre]['Puiss.'] =  0;
foreach ($tabConso as $equipement => $detailsConso) {
	$nomAff = mg::ExtractPartCmd($equipement, 2);
	$type = trim($detailsConso['type']);
	$puissance = (mg::existCmd($equipement, 'Puissance')) ? mg::getCmd($equipement, 'Puissance') : 0;

	if ($oldType != $type || $detail_Lignes != 2) { $numLigne++; }
	$tabAff[$numLigne]['Puiss.'] = round($puissance);

	if ($numLigne == 1) {
		$tabAff[$idxAutre]['Puiss.'] = $puissance;
	} else {
		$tabAff[$idxAutre]['Puiss.'] -= floatval($puissance);
	}
	if ($type == '' || $nomAff == '') { break; }
	$oldType = $type;

//mg::debug();

	// ============================= Mémo N° des Cmd conso et puissance  pour les boutons =============================
	if ($detail_Lignes != 2) {
		$tabAff[$numLigne]['EquiConso'] = (mg::existCmd($equipement, 'Consommation') ? trim(mg::toID($equipement, 'Consommation'), '#') : '');
		$tabAff[$numLigne]['EquiPuis'] = (mg::existCmd($equipement, 'Puissance') ? trim(mg::toID($equipement, 'Puissance'), '#') : '');

		// Calcul pour On/Off si 'Etat' existe
		$equiEtat = '';
		if (mg::existCmd($equipement, 'Etat')) { $equiEtat = (mg::getCmd($equipement, 'Etat') > 0 ? 'ON' : 'OFF'); }
		
		$tabAff[$numLigne]['EquiEtat'] = $equiEtat;
		$tabAff[$numLigne]['EquiAction'] = ($equiEtat == 'ON' ? mg::existCmd($equipement, 'Off') : mg::existCmd($equipement, 'On'));
	}
}

// ======================================== Calcul du HTML de l'année glissante =======================================
$TxtHTML = ''; //'<div>';
$TxtHTML .= DebTableau("(Vue ". ($detail_Lignes == 2 ? 'Regroupée' : 'détaillée') . ")", $width);

// Parcours des lignes
for ($i = 1; $i < $nbLgnAffichage+2; $i++) {
	if ($i != $nbLgnAffichage) {
		$TxtHTML .= LigneMois($i, $consoCoutKWH, $script, $pathRef);
	}
}

// Ligne de clôture du tableau (Répétition Compteur ET Enedis)
$TxtHTML .= LigneMois(1, $consoCoutKWH, $script, $pathRef); // Compteur
$TxtHTML .= LigneMois($nbLgnAffichage, $consoCoutKWH, $script, $pathRef); // Enedis
$TxtHTML .= FinTableau($width);
$TxtHTML .= styleTab();
mg::setInf($equipConso, 'Conso_HTML', $TxtHTML);

file_put_contents($fileExportHTML, $TxtHTML); /* pour debug */
file_put_contents($fileExportJS, $script); 

if (abs($tabAff[$idxAutre]['Puiss.']) < 500) {
	mg::setInf($equipConso, 'Incertitude', $tabAff[$idxAutre]['Puiss.']);
}
//print_r($TxtHTML, false);

// ============================ Mémo des tableaux et variable pour le futur calcul partiel ============================
if (!$traitementPartiel) {
	mg::setVar('_consoEDF_TabAff', $tabAff);
	mg::setVar('_consoEDF_TabNomCol', $tabNomCol);
	mg::setVar('_consoEDF_NbLgn', $nbLgnAffichage);
}
/* ====================================================   FIN   =====================================================*/

/* ====================================================================================================================
//										Mémo des Consommations par équipements / Mois
// ==================================================================================================================*/
function ExtraitConsoMois($mois, $annee, $detail_Lignes, $cpt_Mois, &$numLigne, $autres = false) {
	global $tabConso, $tabNomCol, $tabAff;

	$numLigne = 0;
	$oldType = '';

	// Parcours des lignes
	foreach ($tabConso as $equipement => $detailsConso) {
		$nomAff = mg::ExtractPartCmd($equipement, 2);
		$type = trim($detailsConso['type']);
		$typeResult = ($nomAff == 'Enedis' ? 'E' : 'D'); // EXCEPTION POUR RELEVE ENEDIS DE FIN DE JOURNEE 

		if ($oldType != $type || $detail_Lignes != 2) {
			$numLigne++;
		}
		if ($type == '' || $nomAff == '') { break; }


		// Calcul la conso du mois de l'équipement etat
			$infCmdConsommation = mg::toID($equipement, 'Consommation');
			$consoMois = mg::StatsHisto('#'.$infCmdConsommation.'#', $typeResult, 'M', date("m") - $mois + (date("Y") - $annee)*12, 1);

			// Si ENEDIS Correction du décalage horaire de la mesure via valueDate pour le mois courant
			if ($typeResult == 'E' && date("m") == $mois) {
				mg::getCmd($infCmdConsommation, '', $collectDate, $valueDate);
				$consoMois = $consoMois / (date('d', $valueDate) *24) * (((date('d')+1) * 24));
			}

		// Mémo Nom, Puissance et Consommation
		if ($cpt_Mois == 13) {
			$puissance = (mg::existCmd($equipement, 'Puissance')) ? mg::getCmd($equipement, 'Puissance') : 0;
			$moisAff = 'Mois courant';
			// Nom affichage du mois courant
			$tabNomCol[$cpt_Mois] = date('M Y', strtotime("$annee/$mois/01"));
		} else {
			$moisAff = $mois;
			// Nom affichage du mois glissant
			$tabNomCol[$moisAff] = date('M Y', strtotime("$annee/$mois/01"));
		}

		// Mémo Conso, Nom
		if ($detail_Lignes != 2) {
			$tabAff[$numLigne]['Nom'] = $nomAff;
			$tabAff[$numLigne][$moisAff] = round($consoMois);
			if ($autres) {
				$tabAff[$numLigne]['Puiss.'] = round($puissance);
			}
		} else {
			if ($autres) {
				$tabAff[$numLigne]['Puiss.'] = round($puissance + ($detail_Lignes != 2 ? $tabAff[$numLigne]['Puiss.'] : 0));
			}
			$tabAff[$numLigne]['Nom'] = $type;
			$tabAff[$numLigne][$moisAff] = round($consoMois + ($detail_Lignes == 2 ? $tabAff[$numLigne][$moisAff] : 0));
		}
		$oldType = $type;
	}
}

/* ====================================================================================================================
						Boucle équipements / Mois pour calcul des totalisations mensuelles et annuelles
// ==================================================================================================================*/
function Totalisation($nbLgnAffichage) {
	global $tabNomCol, $tabConso, $tabAff;

//	$numLigne = count($tabConso);
	$idxAutre = $nbLgnAffichage+1;

	$cpt_Ligne = intval(0.0);

	// Parcours des lignes
	foreach ($tabConso as $equipement => $detailsConso) {
		$nomAff = mg::ExtractPartCmd($equipement, 2);
		$cpt_Ligne++;
	if ($cpt_Ligne == 0 || $cpt_Ligne > $nbLgnAffichage) { break; }

		// Parcours des mois, ajustage des noms de colonnes pour le calcul
		foreach ($tabNomCol as $colonne => $value) {
			if ($colonne > count($tabNomCol)-3) { break; }
			if ($colonne >= 1 && $colonne <13) { $nomColonne = $colonne; }
			elseif ($colonne == 13) { $nomColonne = 'Mois courant'; }
			else { $nomColonne = $value; }

			// Cumul mensuel
			if ( $cpt_Ligne != 1 && $cpt_Ligne < $nbLgnAffichage) { $tabAff[$idxAutre][$nomColonne] = intval($tabAff[$idxAutre][$nomColonne]) + intval($tabAff[$cpt_Ligne][$nomColonne]); }

			// Cumul Annuel
			if ( $colonne <= 12) { $tabAff[$cpt_Ligne]['Kw Ann.'] = intval($tabAff[$cpt_Ligne]['Kw Ann.']) + intval($tabAff[$cpt_Ligne][$nomColonne]); }

			// Calcul de l'incertitude de chaque colonne
			if ($cpt_Ligne == $nbLgnAffichage) { $tabAff[$idxAutre][$nomColonne] = intval($tabAff[1][$nomColonne]) - intval($tabAff[$idxAutre][$nomColonne]); }
		} // fin for each tabcolonne
	} // fin foreach tabconso
	$tabAff[$idxAutre]['Nom'] = 'Incertitude ...';
}

/* ====================================================================================================================
														DebTableau
// ==================================================================================================================*/
function DebTableau($type, $width) {
	global $tabNomCol;
	$date = date('d\/m\/Y \à H\hi\m\n', time());
	$nbCol = count($tabNomCol);
	$width = "width=$width";

// Calcul valeur affiché décalé du mois courant	
	$moisCourant = (int)date('m')-1;
	for ($i=1; $i<=12;$i++)
	{
		if ($moisCourant+$i == 12) { 
			$mois[$i] = $tabNomCol[12]; 
		} else {
			$mois[$i] = $tabNomCol[($moisCourant+$i) % 12];
		}
	}
	
$HTML = "
<div>
	<strong>
	<table border=5 cellspacing=5 cellpadding=5 width:100%>
			<tr height=30px>
				<th class=titre colspan=$nbCol>SUIVI DES CONSOMMATIONS ELECTRIQUES $type au $date</th>
			</tr>
	   <tr height=30px>
 		  <td 			class=colNomG>	Nom				</td>
 		  <td $width    class=colNomC> 	$mois[1]		</td>
 		  <td $width 	class=colNomC> 	$mois[2]		</td>
 		  <td $width 	class=colNomC> 	$mois[3]		</td>
 		  <td $width 	class=colNomC> 	$mois[4]		</td>
 		  <td $width 	class=colNomC> 	$mois[5]		</td>
 		  <td $width 	class=colNomC> 	$mois[6]		</td>
 		  <td $width 	class=colNomC> 	$mois[7]		</td>
 		  <td $width 	class=colNomC> 	$mois[8]		</td>
 		  <td $width 	class=colNomC> 	$mois[9]		</td>
 		  <td $width 	class=colNomC> 	$mois[10]		</td>
 		  <td $width 	class=colNomC> 	$mois[11]		</td>
 		  <td $width 	class=colNomC> 	$mois[12]		</td>
 		  <td $width 	class=colNomC> 	$tabNomCol[13]	</td>
 		  <td $width 	class=colNomC> 	$tabNomCol[14]	</td>
 		  <td $width 	class=colNomC> 	$tabNomCol[15]	</td>
 	 	  <td $width 	class=colNomC> 	$tabNomCol[18]	</td>
		  <td $width 	class=colNomC> 	$tabNomCol[16] 	</td>
 		  <td $width 	class=colNomC> 	$tabNomCol[17]	</td>
 		  <td $width 	class=colNomC> 	$tabNomCol[19]	</td>
 		  <td 			class=colNomD colspan=2> Nom	</td>
	   </tr>
	";
return $HTML;
}

/* ====================================================================================================================
													LignesMois
// ==================================================================================================================*/
function LigneMois($numLigne, $consoCoutKWH, &$script, $pathRef) {
	global $tabNomCol, $tabConso, $tabAff, $cmdMaj_Aff;

	$nbLignes = count($tabConso);
	$tabAffLgn = $tabAff[$numLigne];

	$consoMoisCourantTotale = round($tabAff[1]['Mois courant'] / date('d') * date('t'));
	$ConsoMoisCourant = round($tabAffLgn['Mois courant'] / date('d') * date('t')); 
	$PourCentMois = $consoMoisCourantTotale > 0 ? round($ConsoMoisCourant / $consoMoisCourantTotale * 100, 1) : 0;

	$consoAnnuelleTotale = $tabAff[1]['Kw Ann.'];
	$consoAnnuelleEquip = round($tabAffLgn['Kw Ann.']);
	$consoMensuelle = round($consoAnnuelleEquip / 12);
	$coutAnnuel = round($consoAnnuelleEquip * $consoCoutKWH);
	$pourCent = $consoAnnuelleTotale > 0 ? round($consoAnnuelleEquip / $consoAnnuelleTotale * 100, 1) : 0;

	$puissance = round($tabAffLgn['Puiss.']);
	$equiConso = $tabAffLgn['EquiConso'];
	$equiPuis = $tabAffLgn['EquiPuis'];
	$equiEtat = strtoupper($tabAffLgn['EquiEtat']);
	$equiAction = $tabAffLgn['EquiAction'];

	if ($puissance > 2) { $color = '-red'; } else { $color = ''; }

	$btConso = ($equiConso ? "<button class='boutonConso Conso$equiConso'>Conso</button>" : ''); 
	$btPuis = ($equiPuis ? "<button class='boutonPuis Puis$equiPuis'>Puis.</button>" : '');
	$btAction = ($equiAction ? "<img src=\"$pathRef/img/img_Binaire/boutons/$equiEtat.png\" class='Action$equiAction'></button>" : '');

	$lgn = $numLigne % 2;
	if ($numLigne == $nbLignes+1) { $lgn = 'Incertitude'; }
	if ($numLigne == $nbLignes+2 || $numLigne == 1) { $lgn = 'Recap'; }
	if ($numLigne == $nbLignes || $numLigne == 1) { $lgn = 'Recap'; } // ENEDIS

	// Calcul valeurs affichées décalées du mois courant	
	$moisCourant = (int)date('m')-1;
	for ($i=1; $i<=12;$i++)
	{
		if ($moisCourant+$i == 12) { 
			$mois[$i] = $tabAffLgn[12]; 
		} else {
			$mois[$i] = $tabAffLgn[($moisCourant+$i) % 12];
		}
	}
	// Calcul des dates du graph à afficher
	$startDate = date('Y-m-d',strtotime('-1 month',time()));
	$endDate = date('Y-m-d', time());

$HTML = "
	<tr height=24px>
		<td class=colNomG$color> $tabAffLgn[Nom]			</td>
		<td class=lgnMois-$lgn> $mois[1] kW					</td>
		<td class=lgnMois-$lgn> $mois[2] kW					</td>
		<td class=lgnMois-$lgn> $mois[3] kW					</td>
		<td class=lgnMois-$lgn> $mois[4] kW					</td>
		<td class=lgnMois-$lgn> $mois[5] kW					</td>
		<td class=lgnMois-$lgn> $mois[6] kW					</td>
		<td class=lgnMois-$lgn> $mois[7] kW					</td>
		<td class=lgnMois-$lgn> $mois[8] kW					</td>
		<td class=lgnMois-$lgn> $mois[9] kW					</td>
		<td class=lgnMois-$lgn> $mois[10] kW				</td>
		<td class=lgnMois-$lgn> $mois[11] kW				</td>
		<td class=lgnMois-$lgn> $mois[12] kW				</td>
		<td class=lgnMois-Courant-$lgn> $ConsoMoisCourant kW</td>
		<td class=lgnMois-Courant-$lgn> $PourCentMois %		</td>
		<td class=lgnMois-$lgn> $consoMensuelle kW			</td>
		<td class=lgnMois-$lgn> $pourCent %					</td>
		<td class=lgnMensuelle-$lgn> $consoAnnuelleEquip kW	</td>
		<td class=lgnMensuelle-$lgn> $coutAnnuel €			</td>
		<td class=lgnMois-$lgn> $puissance W				</td>
		<td class=colNomD$color> $tabAffLgn[Nom]			</td>
		<td class=colNomD>		$btConso $btPuis $btAction	</td>
	</tr>
	";
	
	$script .= "
	$('.Conso$equiConso').on('click',function(){ graph('$tabAffLgn[Nom]', $equiConso);});
	$('.Puis$equiPuis').on('click',function(){ graph('$tabAffLgn[Nom]', $equiPuis);});

	$('.Action$equiAction').on('click',function(){ jeedom.cmd.execute({id: '$equiAction'});
		setTimeout('jeedom.cmd.execute({id: $cmdMaj_Aff})', 1000);
	});
	";
	
return $HTML;
}

/* ====================================================================================================================
														FinTableau
// ==================================================================================================================*/
function FinTableau($width) {
	global $tabNomCol;
	$width = "width=$width";

	// Calcul valeurs affichées décalées du mois courant	
	$moisCourant = (int)date('m')-1;
	for ($i=1; $i<=12;$i++)
	{
		if ($moisCourant+$i == 12) { 
			$mois[$i] = $tabNomCol[12]; 
		} else {
			$mois[$i] = $tabNomCol[($moisCourant+$i) % 12];
		}
	}
	
	$HTML =	"
	   <tr height=30px>
 		  <td 			class=colNomG> 	Nom				</td>
 		  <td $width    class=colNomC> 	$mois[1]		</td>
 		  <td $width 	class=colNomC> 	$mois[2]		</td>
 		  <td $width 	class=colNomC> 	$mois[3]		</td>
 		  <td $width 	class=colNomC> 	$mois[4]		</td>
 		  <td $width 	class=colNomC> 	$mois[5]		</td>
 		  <td $width 	class=colNomC> 	$mois[6]		</td>
 		  <td $width 	class=colNomC> 	$mois[7]		</td>
 		  <td $width 	class=colNomC> 	$mois[8]		</td>
 		  <td $width 	class=colNomC> 	$mois[9]		</td>
 		  <td $width 	class=colNomC> 	$mois[10]		</td>
 		  <td $width 	class=colNomC> 	$mois[11]		</td>
 		  <td $width 	class=colNomC> 	$mois[12]		</td>
 		  <td $width 	class=colNomC> 	$tabNomCol[13]	</td>
 		  <td $width 	class=colNomC> 	$tabNomCol[14]	</td>
 		  <td $width 	class=colNomC> 	$tabNomCol[15]	</td>
 		  <td $width 	class=colNomC> 	$tabNomCol[18]	</td>
		  <td $width 	class=colNomC> 	$tabNomCol[16] 	</td>
 		  <td $width 	class=colNomC> 	$tabNomCol[17]	</td>
 		  <td $width 	class=colNomC> 	$tabNomCol[19]	</td>
		  <td 			class=colNomD colspan=2> Nom	</td>
	   </tr>
	</table>
	</div>
	</strong>
	";
	return $HTML;
}

/* ====================================================================================================================
														StyleTab
// ==================================================================================================================*/
function styleTab() {

$fontLigne = 'font-size:12px; font-family: Arial;';

$STYLE = "
	<style>
		.boutonConso {
		  user-appearance: none; 
		  border: none;
		  font-weight: bold;
		  font-size: 1.2rem;
		  color: white;
		  background: darkblue;
		}	
	
		.boutonPuis {
		  user-appearance: none; 
		  border: none;
		  font-weight: bold;
		  font-size: 1.2rem;
		  color: white;
		  background: darkgreen;
		}	
		
		.titre{
			text-align: center;
			font-size:22px;
			background-color:#680100;
			color:#ffffff;
		}
/* Ligne titre */				.lgnTitre{text-align:center; font-size:14px; font-weight:bold; background-color:#f56b00; color:#ffffff;}

/* Colonne Nom centre*/			.colNomG{text-align:right; $fontLigne; background-color:#680100; color:#ffffff;}
/* Colonne Nom Droit*/			.colNomD{text-align:left; $fontLigne; background-color:#680100; color:#ffffff;}
/* Colonne Nom gauche*/			.colNomC{text-align:center; $fontLigne; background-color:#680100; color:#ffffff;}
/* Colonne Nom droit rouge*/	.colNomD-red{text-align:left; $fontLigne; background-color:#680100; color:orange;font-weight: bold;}
/* Colonne Nom gauche rouge*/	.colNomG-red{text-align:right; $fontLigne; background-color:#680100; color:orange;}

/* Zone mois Lgn impaire */		.lgnMois-1{text-align:center; $fontLigne; background-color:#ddddddc9; color:#1c1e22;}
/* Zone mois Lgn paire */		.lgnMois-0{text-align:center; $fontLigne; background-color:#ddd; color:#1c1e22;}

/* Col mois courant */			.lgnMois-Courant-1{text-align:center; $fontLigne; background-color:#FFBE84; color:#1c1e22;}
/* Col mois courant */			.lgnMois-Courant-0{text-align:center; $fontLigne; background-color:#FFE3CA; color:#1c1e22;}
/* Col mois courant */			.lgnMois-Courant-Recap{text-align:center; $fontLigne; background-color:#FF7800; color:#1c1e22;}
/* Col mois courant */			.lgnMois-Courant-Incertitude{text-align:center; $fontLigne; background-color:#E3C9A8; color:#1c1e22;}

/* Col mensuel */				.lgnMensuelle-1{text-align:center; $fontLigne; background-color:#FFBE84; color:#1c1e22;}
/* Col mensuel */				.lgnMensuelle-0{text-align:center; $fontLigne; background-color:#FFE3CA; color:#1c1e22;}
/* Col mensuel */				.lgnMensuelle-Recap{text-align:center; $fontLigne; background-color:#FF7800; color:#1c1e22;}
/* Col mensuel */				.lgnMensuelle-Incertitude{text-align:center; $fontLigne; background-color:#E3C9A8; color:#1c1e22;}

/* Zone Synthèse lgn paire */	.lgnSynt-0{text-align:center; $fontLigne; background-color:#FFB9B9; color:#1c1e22;}
/* Zone Synthèse lgn impaire */	.lgnSynt-1{text-align:center; $fontLigne; background-color:#FF7979; color:#1c1e22;}

/* Zone mois lgn Incertitude */	.lgnMois-Incertitude{text-align:center; $fontLigne; background-color:#E3C9A8; color:#1c1e22;}
/* Zone mois Lgn Recap */		.lgnMois-Recap{text-align:center; $fontLigne; background-color:#FF7800; color:#1c1e22;}
	</style>
	";
return $STYLE;
}

?>