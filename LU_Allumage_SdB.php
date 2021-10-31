<?php
/************************************************************************************************************************
LU_Allumage_SdB - 199

Déclencheur nbMvmt à "toujours répéter"
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	// $equipLampes, $equipVeilleuse
	// $infPorte, $infMvmtRdCSdB

//N° des scénarios :

// Variables :
	$nuitSalon = mg::getVar('nuitSalon');
	$nePasDeranger = mg::getVar('nePasDeranger', 0);
	$etatPorte = mg::getCmd($infPorte);
	$lastMvmt = round(mg::lastMvmt($infMvmtRdCSdB, $nbMvmt)/60);

//Paramètres :
	$timer = 2; // Durée en mn avant extinction sans mouvement si porte ouverte
	
/**********************************************************************************************************************
**********************************************************************************************************************/
mg::setRepeatCmd($infMvmtRdCSdB, 'always');

// Lumière

// Porte FERMEE ou mouvement
if ($etatPorte == 1 || $lastMvmt <= $timer) {

	// La nuit OU PasDéranger
    if ($nuitSalon == 2 || $nePasDeranger) { 
		mg::setCmd($equipVeilleuse, 'Slider Intensité', 10); 
		mg::setCmd($equipLampes, 'Off');
	// La journée
	} else { 
		mg::setCmd($equipVeilleuse, 'Slider Intensité', 254); 
		mg::setCmd($equipVeilleuse, 'On');
		mg::setCmd($equipLampes, 'Slider Intensité', 254); 
		mg::setCmd($equipLampes, 'On');
	}

	mg::messageT('', "NuitSalon : $nuitSalon - nePasDeranger : $nePasDeranger - Porte : $etatPorte - lastMvmt : $lastMvmt mn. => ALLUMAGE");
	mg::setCron('', time() + $timer*60);
}

// Porte ouverte ET sans mouvement	on éteint
if ($etatPorte == 0 && ($lastMvmt > $timer || $nuitSalon == 2 || $nePasDeranger)) {
//else {
	mg::setCmd($equipLampes, 'Off');
	mg::setCmd($equipVeilleuse, 'Off');
		mg::setCmd($equipVeilleuse, 'Slider Intensité', 0); 
		mg::setCmd($equipLampes, 'Slider Intensité', 0); 
	
	mg::messageT('', "NuitSalon : $nuitSalon - nePasDeranger : $nePasDeranger - Porte : $etatPorte - lastMvmt : $lastMvmt mn. => EXTINCTION");
}

// Radio SdB pour finir
if (mg::declencheur('Ouverture') && $nuitSalon < 2 && !$nePasDeranger) {
	$IP_JPI = 'HTTP://'.mg::getValSql('_tabUsers', 'JPI', '', 'IP').':8080';
	file_get_contents("$IP_JPI/?action=_radioSdB");
}

?>