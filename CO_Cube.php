<?php
/**********************************************************************************************************************
Cube - 132
Gestion du Cube Magique Xiaomi
Allumage et extinction de la radio Sono, gestion du volumesonore, Allumage/Extinction lumière extérieure
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$equipEcl, $equipEclExt

// N° des scénarios :

//Variables :
	$incrementSon = 0.25;										// Incrément de modification du son en %
	$incrementLumiere = 0.5;									// Incrément de modification de la lumière en %
	$eclEnCours = mg::getCmd($equipEcl, 'Lampe Générale Etat');
	$memoEtat = mg::getCmd($equipEcl, 'Memo Etat');
	
// Paramètres :
	$equipSonos = mg::getParam('Media', 'equipSonos');
	$reveilStationRadio = mg::getParam('Reveil', 'stationRadio');	// Nom de la radio à lancer
	$reveilVolumeRadio = mg::getParam('Reveil', 'volumeRadio');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
	$valEtat = mg::getCmd(mg::declencheur());
	$mouvement = deconz_lumi_sensor_cube_data($valEtat);

$sonosEnCours = mg::getCmd($equipSonos, 'Status');
$sonosEnCours = mg::getCmd($equipSonos, 'Status') == 'Lecture' ;
$volume = max(5, mg::getCmd($equipSonos, 'Volume status'));
if ($volume <= 0) { $volume = $reveilVolumeRadio; }

//=====================================================================================================================
mg::messageT('', "Mouvement ($valEtat) ==> $mouvement - Sonos en cours : $sonosEnCours - Memo lumière : $memoEtat");
//=====================================================================================================================
// Allumage lampes extérieures
//=====================================================================================================================
// tap_twice : Taper deux fois sur la surface où il se trouve.
if ($mouvement == 'tap_twice') {
	if (mg::getCmd($equipEclExt, 'Lampe Générale Etat') == 0) {
			mg::setCmd($equipEclExt, 'Lampe Générale Slider', 99);
	} else {
		mg::setCmd($equipEclExt, 'Lampe Générale Slider', 0);
	}
}

//=====================================================================================================================
// Mise en route de Sonos sur la radio par defaut du réveil :
// shake air : Comme son nom l’indique, il suffit de le secouer.
//=====================================================================================================================
elseif ($mouvement == 'shake_air') {
	mg::Message('', "Met en route ou arrète la radio de Sonos");
	if (!$sonosEnCours) {
		mg::setCmd($equipSonos, 'Volume', $reveilVolumeRadio);
		mg::setCmd($equipSonos, 'Jouer une radio', '', $reveilStationRadio);
	} else {
		mg::setCmd($equipSonos, 'Stop');
		mg::setCmd($equipSonos, 'Volume', 100);
		return;
	}
}
//=====================================================================================================================
// Gestion du volume SONOS et si en route sinon de l'intensité de l'éclairage
//=====================================================================================================================
elseif ($mouvement == 'rotate_left') {
	// DIMINUTION SON
	if ($sonosEnCours) {
		$volume = mg::getCmd($equipSonos, 'Volume status') * (1 - $incrementSon);
		if ($volume < 0) { $volume = 0; }
		mg::Message('', "Diminue le volume de Sono à $volume %");
		mg::setCmd($equipSonos, 'Volume', $volume);
		return;
	}
	// DIMINUTION LUMIERE
	else {
		$memoEtat = $memoEtat * (1 - $incrementLumiere);
		if ($memoEtat < 0) { $memoEtat = 0; }
		mg::setCmd($equipEcl, 'Lampe Générale Slider', $memoEtat);
		mg::Message('', "Diminue l'intensité à $memoEtat %");
	}
}
//=====================================================================================================================
elseif ($mouvement == 'rotate_right') {
	// AUGMENTATION SON
	if ($sonosEnCours) {
		$volume = $volume * (1 + $incrementSon);
		mg::Message('', "Augmente le volume de Sono à $volume %");
		mg::setCmd($equipSonos, 'Volume', $volume);
		return;
	}
// AUGMENTATION LUMIERE
	else {
		if ($memoEtat == 0) { $memoEtat = 10;}
		$memoEtat = $memoEtat * (1 + $incrementLumiere);
		$maxValue = mg::getMinMaxCmd($equipEcl, 'Etat', 'max');
		if ($memoEtat > $maxValue) { $memoEtat = $maxValue; }
		mg::setCmd($equipEcl, 'Lampe Générale Slider', $memoEtat);
		mg::Message('', "Augmente l'intensité à $memoEtat %");
	}
}

// ********************************************************************************************************************
// ************************************************* DECODAGE DU CUBE *************************************************
// ********************************************************************************************************************
function deconz_lumi_sensor_cube_data($buttonevent){
    if($buttonevent == 0) {
      $result = 'shake_air';
    } else if ($buttonevent == '') {
      $result = 'aucun';
    } else if(in_array($buttonevent, array( 65, 66, 68, 69, 72, 74, 75, 77, 80, 81, 83, 84, 89, 90, 92, 93, 96, 98, 99, 101, 104, 105, 107, 108))) {
      $result = 'flip90';
    } else if(in_array($buttonevent, array(257, 258, 259, 260, 260, 261))) {
      $result = 'move';
    } else if(in_array($buttonevent, array(511, 512, 513, 514, 515, 516, 517))) {
      $result = 'tap_twice';
    } else if(in_array($buttonevent, array(130, 133))) {
      $result = 'flip180';
    } else if($buttonevent == 3){
      $result = 'chute';
    } else if($buttonevent == 7000) {
      $result = 'alert';
    } else if(isset($buttonevent)) {
		  if($buttonevent > 0){
			$result = 'rotate_right';
		  } else if($buttonevent < 0) {
			 $result = 'rotate_left';
		  } 
    } 
  return $result;
}

?>