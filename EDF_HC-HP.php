<?php
/**********************************************************************************************************************
EDF_HC-HP - 176

Gestion des Heures creuses / Heures pleines de l'EDF
**********************************************************************************************************************/

// $infCompteur, $equipEDF

//N° des scénarios :

// Variables :
	$indexCompteur = mg::getCmd($infCompteur);
	$lastHP_HC = mg::getVar('HP_HC');
	$lastCompteur = mg::getVar('_LastCompteur');
	$lastTimeConso = mg::getVar('_LastTimeConso');

//Paramètres :
	$Deb_HC1 = mg::getParam('EDF', 'deb_HC1'); // '02:10';
	$Fin_HC1 = mg::getParam('EDF', 'fin_HC1'); //'07:10';
	$Deb_HC2 = mg::getParam('EDF', 'deb_HC2'); //'13:10';
	$Fin_HC2 = mg::getParam('EDF', 'fin_HC2'); //'16:10';

/**********************************************************************************************************************
**********************************************************************************************************************/

// Calcul Incrément
$deltaCompteur = $indexCompteur - $lastCompteur;
$deltaTime = time() - $lastTimeConso;
$increment = $deltaCompteur*60/$deltaTime;
if ($increment < 0 || $increment > 0.25) { $increment = 0; }

// Calcul nom tranche Active 
$HP_HC = mg::TimeBetween(strtotime($Deb_HC1), time(), strtotime($Fin_HC1)) + mg::TimeBetween(strtotime($Deb_HC2), time(), strtotime($Fin_HC2)) ? 'HC' : 'HP';
mg::message('', "Index en cours : $HP_HC - Compteur : $indexCompteur - LastCompteur : $lastCompteur increment : $increment");

// Si changement de tranche pas d'incrément
if ($HP_HC != $lastHP_HC) { $increment = 0; } 

mg::setInf($equipEDF, "Index_$HP_HC", mg::getCmd($equipEDF, "Index_$HP_HC") + $increment);
mg::setInf($equipEDF, "Delta_$HP_HC", $increment);
mg::message('', "Tranche : $HP_HC - increment : $increment");

// Calcul tranche INactive
$HP_HC2 = $HP_HC == 'HC' ? 'HP' : 'HC';
mg::setInf($equipEDF, "Index_$HP_HC2", mg::getCmd($equipEDF, "Index_$HP_HC2"));
mg::setInf($equipEDF, "Delta_$HP_HC2", 0.001);

// ========================================================================================================================
mg::setVar('HP_HC', $HP_HC);
mg::setVar('_LastCompteur', $indexCompteur);
mg::setVar('_LastTimeConso', time());

?>