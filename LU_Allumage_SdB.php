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
	$timer = 3; // Durée en mn avant extinction sans mouvement si porte ouverte
	
/**********************************************************************************************************************
**********************************************************************************************************************/
mg::setRepeatCmd($infMvmtRdCSdB, 'always');


// Radio SdB
if (mg::declencheur('Ouverture') && $nuitSalon < 2 && !$nePasDeranger) {
	$tabUser = mg::getVar('tabUser');
	$IP_JPI = 'HTTP://'.$tabUser['JPI']['IP'].':8080';
	file_get_contents("$IP_JPI/?action=_radioSdB");
}

// Lumière

// Porte FERMEE ou mouvement
if ($etatPorte == 1 || $lastMvmt <= $timer) {

	// La nuit OU PasDéranger
    if ($nuitSalon == 2 || $nePasDeranger) { 
		mg::setCmd($equipVeilleuse, 'Slider Intensité', 10); 
		mg::setCmd($equipLampes, 'Slider Intensité', 0); 
	// La journée
	} else { 
		mg::setCmd($equipVeilleuse, 'Slider Intensité', 254); 
		mg::setCmd($equipLampes, 'Slider Intensité', 254); 
	}

	mg::messageT('', "NuitSalon : $nuitSalon - nePasDeranger : $nePasDeranger - Porte : $etatPorte - lastMvmt : $lastMvmt mn. => ALLUMAGE");
	mg::setCron('', time() + $timer*60);
}

// Porte ouverte et sans mouvement	on éteint
else {
//	mg::setCmd($equipLampes, 'Off');
	mg::setCmd($equipVeilleuse, 'Slider Intensité', 0); 
//	mg::setCmd($equipVeilleuse, 'Off');
	mg::setCmd($equipLampes, 'Slider Intensité', 0);
	mg::messageT('', "NuitSalon : $nuitSalon - nePasDeranger : $nePasDeranger - Porte : $etatPorte - lastMvmt : $lastMvmt mn. => EXTINCTION");
}

?>