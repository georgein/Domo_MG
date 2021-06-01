<?php
/************************************************************************************************************************
LU_Allumage_SdB - 199

**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	// $equipLampes, $equipVeilleuse, $equipLp_2, $equipLp_3
	// $infPorte


//N° des scénarios :

// Variables :

//Paramètres :

/**********************************************************************************************************************
**********************************************************************************************************************/
$intensite = 0;
$intensiteVeilleuse = 0;

$lastMvmt = round(mg::lastMvmt($infNbMvmtRdCSdB, $nbMvmt)/60);
$nuitSalon = mg::getVar('nuitSalon');
$etatPorte = mg::getCmd($infPorte);

// Porte fermée
if ($etatPorte == 1) {
    if ($nuitSalon == 2 ) {
//		$intensite = 0;
		$intensiteVeilleuse = 10;
	} elseif ($nuitSalon == 1 || ($nuitSalon == 0 && $lastMvmt < 10)) {
		$intensite = 254;
		$intensiteVeilleuse = 254;
    }
}

mg::setCmd($equipVeilleuse, 'Slider Intensité', $intensiteVeilleuse);
mg::setCmd($equipLp_2, 'Slider Intensité', $intensite);
mg::setCmd($equipLp_3, 'Slider Intensité', $intensite);

mg::messageT('', "NuitSalon : $nuitSalon - Porte : $etatPorte - LastMvmt : $lastMvmt - " . ($intensiteVeilleuse == 1 ? "Passage en veilleuse de la SdB à $intensiteVeilleuse." : "Passage des lampes SdB à $intensite."));

mg::debug(0);
//mg::JPI('SCENARIO', '_radioSdB');

?>