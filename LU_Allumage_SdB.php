<?php
/************************************************************************************************************************
LU_Allumage_SdB - 199

**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	// $equipLampes, $equipVeilleuse, $equipLp_2, $equipLp_3
	// $infPorte


//N° des scénarios :

// Variables :
	$nuitSalon = mg::getVar('nuitSalon');
	$timer = 5; // Durée en mn avant extinction sans mouvement

//Paramètres :

/**********************************************************************************************************************
**********************************************************************************************************************/
$intensite = 0;
$intensiteVeilleuse = 0;

if(mg::declencheur('Ouverture') && $nuitSalon < 2) file_get_contents("HTTP://192.168.2.51:8080/?action=_radioSdB");

$etatPorte = mg::getCmd($infPorte);
$lastMvmt = round(mg::lastMvmt($infNbMvmtRdCSdB, $nbMvmt)/60);

// Porte fermée
if ($etatPorte > 0) {
    if ($nuitSalon == 2 ) {
		$intensiteVeilleuse = 10;
	} elseif ($nuitSalon == 1 || ($nuitSalon == 0 && $lastMvmt < $timer)) {
		$intensite = 254;
		$intensiteVeilleuse = 254;
    }
}

// Action sur les lampes
mg::setCmd($equipVeilleuse, 'Slider Intensité', $intensiteVeilleuse);
mg::setCmd($equipLp_2, 'Slider Intensité', $intensite);
mg::setCmd($equipLp_3, 'Slider Intensité', $intensite);

mg::messageT('', "NuitSalon : $nuitSalon - Porte : $etatPorte - LastMvmt ($lastMvmt < $timer ??) - " . ($intensiteVeilleuse > 0 ? "Passage en veilleuse de la SdB à $intensiteVeilleuse." : "Passage des lampes SdB à $intensite."));

?>