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
	$incrementLumiere = 0.10;									// Incrément de modification de la lumière en %
	$eclEnCours = mg::getCmd($equipEcl, 'Lampe Générale Etat');
	$memoEtat = mg::getCmd($equipEcl, 'Memo Etat');
	
// Paramètres :
	$equipSonos = mg::getParam('Media', 'equipSonos');
	$reveilStationRadio = mg::getParam('Reveil', 'stationRadio');	// Nom de la radio à lancer
	$reveilVolumeRadio = mg::getParam('Reveil', 'volumeRadio');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$declencheur = mg::getTag('#trigger#');

$mouvement = deconz_lumi_sensor_cube_data(mg::getCmd($declencheur));

$sonosEnCours = mg::getCmd($equipSonos, 'Status');
$sonosEnCours = mg::getCmd($equipSonos, 'Status') == 'Lecture' ;
$volume = max(5, mg::getCmd($equipSonos, 'Volume status'));
if ($volume <= 0) { $volume = $reveilVolumeRadio; }

//=====================================================================================================================
mg::MessageT('', "Mouvement ==> $mouvement");
//=====================================================================================================================
// Allumage lampes extérieures
//=====================================================================================================================
// tap_twice : Taper deux fois sur la surface où il se trouve.
if ($mouvement == 'tap_twice') {
	if (mg::getCmd($equipEclExt, 'Lampe Générale Etat') == 0) {
			mg::setCmd($equipEcl, 'Lampe Générale Slider', 99);
	} else {
		mg::setCmd($equipEcl, 'Lampe Générale Slider', 0);
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
		if ($memoEtat > 99) { $memoEtat = 99; }
		mg::setCmd($equipEcl, 'Lampe Générale Slider', $memoEtat);
		mg::Message('', "Augmente l'intensité à $memoEtat %");
	}
}

/*//=====================================================================================================================
// flip90 : lorsqu’on pivote une face sur une autre à 90°.
//	case 'flip90':
		break;
//=====================================================================================================================
// flip180 : Même chose que le précédent sauf à 180°.
//	case 'flip180':
		break;
//=====================================================================================================================
// free_fall : Chute du cube.
	case 'free_fall':
		break;
//=====================================================================================================================
// move : lorsque l’on bouge le cube sur une surface plane.
//	  case 'move':
		  break;
//=====================================================================================================================
// alert : dès que l’on touche ou fait une action sur le cube, l’action se déclenche donc vraiment trop sensible à part lorsqu’on l’utilise dans un mode alarme.
	  case 'alert':
		mg::message('', 'test alert');
		break;
//=====================================================================================================================
//}*/

function deconz_lumi_sensor_cube_data($buttonevent){
    if(in_array($buttonevent, array(1002, 1003, 1004, 1005, 2001, 2003, 2004, 2006, 3001, 3002, 3005, 3006, 4001, 4002, 4005, 4006, 5001, 5003, 5004, 5006, 6002, 6003, 6004, 6005))){
      $result = 'flip90';
    }else if(in_array($buttonevent, array(1000, 2000, 3000, 4000, 5000, 6000))){
      $result = 'move';
    } else if(in_array($buttonevent, array(1001, 2002, 3003, 4004, 5005, 6006))){
      $result = 'tap_twice';
    } else if(in_array($buttonevent, array(1006, 2005, 3004, 4003, 5002, 6001))){
      $result = 'flip180';
    } else if($buttonevent == 7007){
      $result = 'shake_air';
    } else if($buttonevent == 7008){
      $result = 'free_fall';
    } else if($buttonevent == 7000){
      $result = 'alert';
    } else if(strlen($buttonevent) != 4 || substr($buttonevent, 1, 2) != '00'){
		  if($buttonevent > 0){
			$result = 'rotate_right';
		  } else if($buttonevent < 0){
			 $result = 'rotate_left';
		  } 
    } 
//mg::message('', "$buttonevent - $buttonevent2 - $result");
  return $result;
}

?>