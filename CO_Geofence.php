<?php
/**********************************************************************************************************************
Geofence - 123
Signale l'approche d'un user
**********************************************************************************************************************/
// SPECIAL DEBUG - REGENERE $tabGeofence via l'historique
// Infos, Commandes et Equipements :
//	$equipPresence

// N° des scénarios :

//Variables :
	$latLng_Home = '43.35071300,5.20839500';
	$homeSSID = ' Livebox-MG';					// Valeur contenue dans le SSID de 'HOME'

	$destinatairesSOS = "Log, SMS:@MG, Mail:MG";// Destinataires du SOS
	$timingSOS = 900;						// Durée de pause 'normale' avant d'envoi un SOS

	$destinataires = 'Log, TTS:GOOGLECAST';	// Destinataire du message d'annonce de proximité
	$coeffDist = 0.9;						// Annonce de proximité faite si DistCouranteUser * $CoeffDist < OldDistUser
	$DistanceMax = 0.25;					// Distance minimum pour les alertes de proximité

	$refresh = 60;							// Période de rafraichissement de la page HTML en seconde
	$marqueurSize = 20;						// Taille des icones de marquage trajet
	$layerDefaut = 'OpenStreetMap_France';	// Nom du layer par defaut OpenTopoMap, GeoportailFrance_maps, OpenStreetMap_France
	$epaisseur = 6;							// Epaisseur de la trace
	$colorNR = 'yellow';
	$colorMG = 'red';
	$colorPause = 'yellow';
	$colorVoiture = 'green';
	$colorEntrainement = 'red';
	$distanceSleep = 30;					// Distance minimum en mètre à parcourir pour sortir de la pause
	$pauseMin = 2;							// Durée minimum de la Pause en mn pour l'afficher
	$coeffEcart = 1.115; // 1.115; 1.24; 1.081;					// Coeff à appliquer aux écarts (distance entre deux points)
	$limiteEcart = 0.14;						// Ecart maximum à la mn sinon on saute le point
	$coeffDeniveles = 1.076;// 1.02; 1.075; 0.85;					// Coeff à appliquer aux dénivelés


	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
	$fileExport = (getRootPath() . "$pathRef/util/geofence.html");

// Paramètres :

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$declencheur = mg::getTag('#trigger#');
$userAppel = mg::ExtractPartCmd($declencheur, 3);

$dateSQL = date('Y\-m\-d', time()); // Date du jour au format SQL

$tabGeofence = array();
$tabGeofence = mg::getVar('tabGeofence');
$userActif = '';

// ************************************************ PARCOURS DES USERS ************************************************
foreach ($tabUserGeofence as $user => $detailsUser) {
	if (trim($user == '')) { continue; }
	$id = $detailsUser['ID'];
	$infUser = mg::toHuman($id);
	$userPresent = mg::getCmd($equipPresence, $user);
	if ($userPresent == 0) {
		if ($tabGeofence[$user]['debTime'] > 0) { $userActif = $user; }
		if ($tabGeofence[$user]['cloture'] > 0 || $tabGeofence[$user]['dateActive'] != $dateSQL) {
			$tabGeofence[$user]['dateActive'] = $dateSQL;
		}
	}
	makeLatLng($tabGeofence, $user, $id, $latLng_Home, $distanceSleep, $pauseMin, $coeffEcart, $coeffDeniveles, $destinatairesSOS, $timingSOS, $limiteEcart);
	mg::messageT('', "! User : $user - Présent : $userPresent - debTime : " . date('d\/m \à H\hi\m\n', $tabGeofence[$user]['debTime']) . " - Cloture : " . date('d\/m \à H\hi\m\n', $tabGeofence[$user]['cloture']));

	// ******************************************* SIGNALEMENT DE LA PROXIMITE ****************************************
	if ($userAppel == $user) {
		$value = mg::getCmd($infUser);
		$value_ = explode(',', $value);
		$latLng_Home_ = explode(',', $latLng_Home);
		$dist = mg::getDistance($latLng_Home_[0], $latLng_Home_[1], $value_[0], $value_[1], 'k');

		// ******************************************** MAJ WIDGET GOOGLE MAP *****************************************
		$tmp = mg::getParamWidget($infUser, '');

		$tmp['from'] = $value_[0] . ', ' . $value_[1];
		mg::setParamWidget($infUser, '', $tmp);
		$tmp['to'] = $latLng_Home_[0] . ', ' . $latLng_Home_[1];
		mg::setParamWidget($infUser, '', $tmp);

		 // ********** Si message user avec SSID Home on positionne la distance à -1 pour Alarme Présence et on saute *********
		$SSID = trim($value_[4]);
		if (strpos(" $SSID", $homeSSID) !== false) {
			mg::setVar("dist_Tel-$user", -1);
			continue;
		}

		// ****************************************** ENVOIS D'UN SOS MANUEL ******************************************
		if (trim($value_[5]) == 'SOS') {
			mg::message($destinatairesSOS, "SOS MANUEL de $user, Coordonnées (" . $value_[0] . ', ' . $value_[1] . "). VOIR :  https://georgein.dns2.jeedom.com/mg/util/geofence.html");
		}

		// ************************************************************************************************************
		mg::setVar("dist_Tel-$user", $dist);
		if ($dist < 0.15 || $userPresent > 0) {
			mg::unsetVar("_OldDist_$user");
			continue;
		}
		mg::messageT('', "! Signalement de proximité de $user à $dist km");
		$oldDist = mg::getVar("_OldDist_$user", $dist);

		if ($dist < $oldDist * $coeffDist && $dist > $DistanceMax) {
			$distTxt = str_replace('.', ',', round($dist,1));
			mg::Message($destinataires, "'$user' en approche à $distTxt kilomètres.");
			mg::setVar("_OldDist_$user", $dist);
		} else {
			mg::setVar("_OldDist_$user", max($dist, $oldDist));
		}
	}
}

//	******************************************* ECRITURE DU FICHIER HTML **********************************************
	mg::messageT('', "! GENERATION HTML ET ENREGISTREMENT DE GEOFENCE");
	mg::setVar('tabGeofence', $tabGeofence);
	//mg::message('', print_r($tabGeofence, true));

	if (!$userActif) { $userActif = $user; }
	file_put_contents($fileExport, HTML($tabGeofence, $userActif, $latLng_Home, $refresh, $layerDefaut, $marqueurSize, $distanceSleep, $pauseMin, $epaisseur, $colorMG, $colorNR, $colorPause, $colorEntrainement, $colorVoiture, $pathRef));

// ********************************************************************************************************************
function HTML($tabGeofence, $userActif, $latLng_Home, $refresh, $layerDefaut, $marqueurSize, $distanceSleep, $pauseMin, $epaisseur, $colorMG, $colorNR, $colorPause, $colorEntrainement, $colorVoiture, $pathRef) {

$tailleBoutons = '96px';
// Page HTML
$geofence = "
<!DOCTYPE html>
<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"fr\">
	<head>
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\" />
		<link rel=\"stylesheet\" href=\"$pathRef/ressources/leaflet/leaflet.css\" />
		<script src=\"$pathRef/ressources/leaflet/leaflet.js\"></script>
		<link rel=\"stylesheet\" href=\"$pathRef/ressources/leaflet/easy-button.css\" />
		<script src=\"$pathRef/ressources/leaflet/easy-button.js\"></script>
		<META HTTP-EQUIV='refresh' CONTENT='180; URL=$pathRef/util/geofence.html'>
	</head>

	<style>
		html, body {
			height: 100%
		}
		.leaflet-marker-icon.leaflet-interactive {
			border-radius: 50% /* Mise cercle des icones */
		}

		.leaflet-bar button, .leaflet-bar button:hover, .leaflet-bar a, .leaflet-bar a:hover/*, .leaflet-touch*/, .leaflet-control-layers-toggle {
			width: $tailleBoutons!important; /* Largeur bouton image de zoom */
			height: $tailleBoutons!important; /* Hauteur bouton image de zoom */
			line-height: $tailleBoutons;
		}

		.leaflet-control-zoom-in, .leaflet-control-zoom-out {
			font: bold $tailleBoutons 'Lucida Console', Monaco, monospace;
		}


		.leaflet-bar, .leaflet-bar button:first-of-type, .leaflet-bar button:last-of-type, .leaflet-bar a:first-child, .leaflet-bar a:last-child {
		  border-radius: 50%;
		}

		.leaflet-bar {
			box-shadow: 0 15px 15px rgba(0,0,0,0.65);
		}

	.leaflet-popup-content {
		margin: 5px 10px;
		line-height: 1.4;
		font-size: 22px!important;
		width:600px!important;
		}

	.leaflet-tooltip {
		font-size: 22px;
	}

	</style>

	<body onload=\"initialize()\">
		<div id=\"map\" style=\"width:100%; height:100%\"></div>
	</body>
</html>

<script type=\"text/javascript\">
// ***************** LISTE DES LAYERS SUR : http://leaflet-extras.github.io/leaflet-providers/preview/ ****************
// ***************** SITE DE LEAFLET : https://leafletjs.com/ *****************

function initialize() {

	var latLng_Home = L.latLng($latLng_Home);
	var map = L.map('map').setView(latLng_Home, 15	);
	L.control.scale().addTo(map); // Pose de l'échelle
	// Options de la polyline : https://leafletjs.com/reference-1.6.0.html#path

// ********************************************* MARQUEUR POINT DE TRACE **********************************************
	var iconEntrainement = new L.Icon({
	  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-$colorEntrainement.png',
	  iconSize: [$marqueurSize, $marqueurSize],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
	  popupAnchor: [1, -1.2*$marqueurSize],
	});

	var iconVoiture = new L.Icon({
	  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-$colorVoiture.png',
	  iconSize: [$marqueurSize, $marqueurSize],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
	  popupAnchor: [1, -1.2*$marqueurSize],
	});

/*	var iconPause = new L.Icon({
	  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-$colorPause.png',
	  iconSize: [$marqueurSize, $marqueurSize],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
	  popupAnchor: [1, -1.2*$marqueurSize],
	});*/

	var iconPause = new L.Icon({
	  iconUrl: \"$pathRef/img/img_Binaire/presences/sleepingEmoji.png\",
	  iconSize: [$marqueurSize*2, $marqueurSize*2],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
//	  shadowSize: [41, 41]
	  popupAnchor: [1, -1.2*$marqueurSize],
	});


/*	L.marker(latLng_Home, {icon: iconEntrainement}).addTo(map)
	//	.bindTooltip(\"Maison Ensues\", {permanent: true, direction: 'bottom'}); // Affichage permanent
		.bindPopup('Maison Ensues'); // Affichage click*/
";

$polylineAll = '';
$numUser = 0;
foreach ($tabGeofence as $user => $detailsUser) {
	if ($detailsUser['latlngs'] == '') { continue; } // Si user absent

	$latlngs = $detailsUser['latlngs'];
	$complements = $detailsUser['complements'];

	$debTime = intval($detailsUser['debTime']);
	$lastTime = intval($detailsUser['lastTime']);
	$lastPcBatterie = intval($detailsUser['lastPcBatterie']);
	$lastSSID = $detailsUser['lastSSID'];

	$distanceTotale = round($detailsUser['distanceTotale'], 1);
	$denivelePlus = round($detailsUser['denivelePlus'], 0);
	$deniveleMoins = round($detailsUser['deniveleMoins'], 0);
	$cloture = intval(($detailsUser['cloture'] == 0 ? $detailsUser['lastTime'] : $detailsUser['cloture']));
	$vitesseMoyenne = round($detailsUser['vitesseMoyenne'], 1);

	$debTimeStr = date('d/m \&#224;\ H:i', $debTime);
	$lastTimeStr = date('d/m \&#224;\ H:i', $lastTime);
	$clotureStr = date('d/m \&#224;\ H:i', $cloture);
	$dureeTotaleStr = gmdate('H:i', intval($detailsUser['dureeTotale']));
	$dureeMouvementStr = gmdate('H:i', intval($detailsUser['dureeMouvement']));
	$sommePauseStr = gmdate('H:i', intval($detailsUser['sommePause']));

	$polylineAll .= "polyline$user,";
	$imgBouton = "$user.png";
	$colorUser = $user == 'MG' ? $colorMG : $colorNR;
	$numUser++;

// ********************************************************************************************************************
// ********************************************************* DEBUT USER ***********************************************
// ********************************************************************************************************************
$geofence .=
	"
	// *********************************************** DEBUT DE LA TRACE $user ****************************************
	var latlngs = [$latlngs];
	var complements = [$complements];
	var dureePause = 0;

	var polyline$user = L.polyline(latlngs, {color:'$colorUser', weight:'$epaisseur' }).addTo(map);

	var iconUser = new L.icon({
		iconUrl: \"$pathRef/img/img_Binaire/presences/$user.png\",
		shadowUrl:	  'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
		iconSize:	  [48, 48], // taille de l'icone
		iconAnchor:	  [24, 48*$numUser-48], // point de l'icone qui correspondra à la position du marker
		shadowSize:	  [96, 96], // taille de l'ombre
		shadowAnchor: [48, 96], // idem pour l'ombre
		popupAnchor:  [-3, 42 -48*$numUser] // point depuis lequel la popup doit s'ouvrir relativement à l'iconAnchor
	});

	// ********************************************* MARQUEUR SUR LA TRACE ********************************************
	var sommeEcarts = 0;

	for ( var i=1; i < latlngs.length-1; ++i ) {
		var time = latlngs[i][2]*1000;

		// Conversion date en string
		var date = new Date(time);
		annee = date.getFullYear();
		moi = date.getMonth();
	   mois = new Array('Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre');
		j = date.getDate();
		jour = date.getDay();
		jours = new Array('Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi');
		h = date.getHours();
		if(h<10) { h = '0'+h; }
		m = date.getMinutes();
		if(m<10) { m = '0'+m; }
		s = date.getSeconds();
		if(s<10) { s = '0'+s; }
		dateString = j+' '+mois[moi] +' '+annee + ' &#224; ' + h + ':' + m;
		// Fin de la conversion

		var coordonnee = latlngs[latlngs.length-1].toString();
		var	 latLng = coordonnee.split(',');
		latLng_ = L.latLng(latlngs[latlngs.length-1]);
		var lat = Math.round(latLng[0]*1000000)/1000000;
		var long = Math.round(latLng[1]*1000000)/1000000;

		var ecart = parseFloat(complements[i][0]);
		var dureeEcart = parseFloat(complements[i][1])/60;
		var altitude = parseInt(complements[i][2]);
		var activite = complements[i][3];
		var pcBatterie = parseInt(complements[i][4]);


		// Marqueur ENTRAINEMENT
		if (activite == 'I' || activite == 'E') {
			sommeEcarts = sommeEcarts + ecart;
			L.marker( latlngs[i], {icon: iconEntrainement} ).bindTooltip( '<center> $user : ' + dateString + ' - '
				+ Math.round(sommeEcarts*100)/100 + ' km - Alt ' + altitude + ' m - Bat. ' + pcBatterie + ' %'
				+ '<br>Coordonn&#232;es : (' + lat + ' , ' + long + ')').addTo( map )
			dureePause = 0;
		}

		// Marqueur VOITURE
		if (activite == 'V') {
			L.marker( latlngs[i], {icon: iconVoiture} ).bindTooltip('$user : '	+ dateString + ' - Bat. ' + pcBatterie + ' %').addTo( map );
			dureePause = 0;
		}

		// Marqueur de PAUSE
		if (activite == 'I' && sommeEcarts > 0) {
			dureePause = dureePause + parseFloat(complements[i+1][1])/60; //dureeEcart;
			// En fin de pause on affiche l'icone
			if (dureePause > $pauseMin && complements[i+1][3] != 'I') {
			   L.marker( latlngs[i], {icon: iconPause} ).bindTooltip('$user : '	+ dateString + ' - PAUSE de ' + Math.round(dureePause) + ' mn - Alt ' + altitude + ' m').addTo( map );
			}
		}
	}

	// Icone du User sur la carte
	var message = '';
		if ($debTime != 0) {
		message = ' <center>********************* ENTRAINEMENT $user *********************'
		+ '<br>du $debTimeStr au $clotureStr'
		+ '<br>Dur&eacute;e $dureeTotaleStr, en mouvement $dureeMouvementStr, ($sommePauseStr de pause)'
		+ '<br>Dist ' + Math.round($distanceTotale*100)/100 + ' km - d&eacute;nivel&eacute;s +' + $denivelePlus + ' m / ' + $deniveleMoins + ' m - Vitesse		$vitesseMoyenne km/h'
		+ '<br> -------------------------------------------------------------------------------<br>';
		}
		message =	message + '<center>$user : $lastTimeStr : Batterie $lastPcBatterie % - $lastSSID'
					+'<br> Coordonn&#232;es : (' + lat + ' , ' + long + ')';

	L.marker(latLng_, {icon: iconUser}).addTo(map).bindTooltip( message ).openPopup(); // POPUP : bindPopup

	// ******************************************** BOUTONS de zoom $user *********************************************
		L . easyButton ( '<img src = \"$pathRef/img/img_Binaire/presences/$imgBouton\">' ,  function ( btn ,	map ) {
			var	 $user	=  L.latLng(latlngs[latlngs.length-1]);
			var group = new L.featureGroup([polyline$user]);
			map.fitBounds(group.getBounds());
		} ) . addTo ( map ) ;
	// ********************************************* FIN DE LA TRACE $user ********************************************
	";
}

// ********************************************************************************************************************
// ********************************************************** FIN USER ************************************************
// ********************************************************************************************************************
$geofence .= "

	// ********************************************* BOUTONS de zoom All **********************************************
	L . easyButton ( '<img src = \"$pathRef/img/img_Binaire/presences/monde.png\">' ,	 function ( btn ,	 map ) {
	var group = new L.featureGroup([$polylineAll]);
	map.fitBounds(group.getBounds());
} ) . addTo ( map ) ;

	// ************************************** ZOOM AU CHARGEMENT SUR UserActif ***************************************
	var group = new L.featureGroup([polyline$userActif]);
	map.fitBounds(group.getBounds());

	// *************************************** POSE DU REPERE HOME (REGION RONDE) *************************************
	var influence = L.circle(latLng_Home, 100, {
		'color': 'red',
		'fill': true,
		'fillColor': 'red',
		'fillOpacity': 0.2,
	}).addTo(map);

	// ****************************************************************************************************************
	// ****************************************************************************************************************

	// **************************************** DEFINITIONS DES LAYERS PROPOSES ***************************************

var OpenStreetMap_France = L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
	maxZoom: 20,
	attribution: '&copy; Openstreetmap France | &copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors'
});

	var osmLayer = L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
		attribution: '© OpenStreetMap contributors',
		maxZoom: 19,
   });

	var OpenTopoMap = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
		maxZoom: 17,
		attribution: 'Map data: &copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors, <a href=\"http://viewfinderpanoramas.org\">SRTM</a> | Map style: &copy; <a href=\"https://opentopomap.org\">OpenTopoMap</a> (<a href=\"https://creativecommons.org/licenses/by-sa/3.0/\">CC-BY-SA</a>)'
	});

	var Esri_WorldImagery = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
		attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
		minZoom: 0,
		maxZoom: 19,
	});

	var GeoportailFrance_ignMaps = L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: '<a target=\"_blank\" href=\"https://www.geoportail.gouv.fr/\">Geoportail France</a>',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 18,
		apikey: 'choisirgeoportail',
		format: 'image/jpeg',
		style: 'normal'
	});

	var GeoportailFrance_parcels = L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=CADASTRALPARCELS.PARCELS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: '<a target=\"_blank\" href=\"https://www.geoportail.gouv.fr/\">Geoportail France</a>',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 20,
		apikey: 'choisirgeoportail',
		format: 'image/png',
		style: 'bdparcellaire'
	});

	var GeoportailFrance_maps = L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS.SCAN-EXPRESS.STANDARD&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: '<a target=\"_blank\" href=\"https://www.geoportail.gouv.fr/\">Geoportail France</a>',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 18,
		apikey: 'choisirgeoportail',
		format: 'image/jpeg',
		style: 'normal'
	});

	var GeoportailFrance_orthos = L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: '<a target=\"_blank\" href=\"https://www.geoportail.gouv.fr/\">Geoportail France</a>',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 19,
		apikey: 'choisirgeoportail',
		format: 'image/jpeg',
		style: 'normal'
	});
/********** FIN INIT DES LAYERS **********/

// ******************************************* AFFICHAGE DU CHOIX DES LAYERS ******************************************
	map.addLayer($layerDefaut); // Le layer par dÃ©faut
	map.addControl(new L.Control.Layers( {

		'OpenStreetMap_France': OpenStreetMap_France,
		'OpenStreetMap': osmLayer,
		'OpenTopoMap': OpenTopoMap,
		'Esri_WorldImagery': Esri_WorldImagery,

		'GeoportailFrance_ignMaps': GeoportailFrance_ignMaps,
		'GeoportailFrance_parcels': GeoportailFrance_parcels,
		'GeoportailFrance_maps': GeoportailFrance_maps,
		'GeoportailFrance_orthos': GeoportailFrance_orthos,
		}, {})
	);
	// ************************************ FIN DE L'AFFICHAGE DU CHOIX DES LAYERS ************************************

}
</script>

";

return $geofence;
}

/*********************************************************************************************************************/
/*******************************************Extrait les LatLong de l'historiques (pour debug) ************************/
/*********************************************************************************************************************/
function makeLatLng(&$tabGeofence, $user, $id, $latLng_Home, $distanceSleep, $pauseMin, $coeffEcart, $coeffDeniveles, $destinatairesSOS, $timingSOS, $limiteEcart) {
	$values = array();
	$tabGeofence[$user]['latlngs'] = '';
	$tabGeofence[$user]['complements'] = '';
	$tabGeofence[$user]['distanceTotale'] = 0;
	$tabGeofence[$user]['dureeTotale'] = 0;
	$tabGeofence[$user]['dureeMouvement'] = 0;
	$tabGeofence[$user]['sommePause'] = 0;
	$tabGeofence[$user]['denivelePlus'] = 0;
	$tabGeofence[$user]['deniveleMoins'] = 0;
	$tabGeofence[$user]['debTime'] = 0;
	$tabGeofence[$user]['cloture'] = 0;

//	$etatMode = '';
	$timeCourante = 0;
	$dureePause = 0;
	$ecart = 0;
	$dureeEcart = 0;
	$altitude = 0;
	$oldAltitude = 0;
	$oldlatlng = $latLng_Home;

	 //  $tabGeofence['MG']['dateActive'] = '2020-12-12'; /* POUR LES TEST ***********************/

	$dateSQL = $tabGeofence[$user]['dateActive']; // Date de debut au format SQL
	$sql = "
		SELECT value, datetime
		FROM `history`
		WHERE `cmd_id` = '$id' AND `datetime` LIKE '%$dateSQL%'
		ORDER BY `datetime` ASC
		LIMIT 500
	";
	mg::message('', $sql);
	$result = DB::Prepare($sql, (array)$values, DB::FETCH_TYPE_ALL);

	for($i=1; $i<count($result)-1; $i++) {
		$timeCourante = strtotime($result[$i]['datetime']);
		$latlng_ = explode(',', $result[$i]['value']); // Decomposition de value
		$latlng =  $latlng_[0] . ',' . $latlng_[1];
		$lastAltitude = floatval(intval($latlng_[2]));
		$pcBatterie = intval($latlng_[3]);
		$SSID = trim($latlng_[4]);
		$activite = strtoupper(substr(trim($latlng_[5]), 0, 1));

		// On saute si coordonnées pas changées
		if ($oldlatlng == $latlng && $SSID != 'Pas de SSID') { continue; }

		$oldlatlngs_ = explode(',', $oldlatlng);
		$latlngs_ = explode(',', trim(trim($latlng,'['),']'));
		$ecart = round(mg::getDistance($latlngs_[0], $latlngs_[1], $oldlatlngs_[0], $oldlatlngs_[1], 'k') * $coeffEcart, 3);
		$dureeEcart = (strtotime($result[$i]['datetime']) - strtotime($result[$i-1]['datetime']));

		// Calcul des valeurs du point "suivant"
		$latlng_2_ = explode(',', $result[$i+1]['value']);
		$activite_2 = strtoupper(substr(trim($latlng_2_[5]), 0, 1));
		$ecart_2 = round(mg::getDistance($latlngs_[0], $latlngs_[1], $latlng_2_[0], $latlng_2_[1], 'k') * $coeffEcart, 3);
		$dureeEcart_2 = (strtotime($result[$i+1]['datetime']) - strtotime($result[$i]['datetime']));

		// on saute si point courant et suivant trop à l'écart
		if (($activite == 'E' ||$activite == 'I') && $ecart / $dureeEcart * 60 > $limiteEcart && $ecart_2 / $dureeEcart_2 * 60 > $limiteEcart) {
			mg::message('', "limite ecart : $limiteEcart ==> $ecart / $dureeEcart * 60 = " . $ecart / $dureeEcart*60 ." && $ecart_2 / $dureeEcart_2 * 60 = " . $ecart_2 / $dureeEcart_2*60);
			continue;
		}

		$latLng_Home_ = explode(',', $latLng_Home);
		$distHome = mg::getDistance($latLng_Home_[0], $latLng_Home_[1], $oldlatlngs_[0], $oldlatlngs_[1], 'k');

		// ********************************************** ENTRAINEMENTS ***********************************************
		if ($SSID == 'Pas de SSID' && ($activite == 'E' || $activite == 'I')) {

			// debTime
			if ($activite == 'E' && $tabGeofence[$user]['debTime'] <= 0) {
				$tabGeofence[$user]['debTime'] = $timeCourante;
				$tabGeofence[$user]['cloture'] = 0;
			} else {
			// Entrainement en cours
				if ($tabGeofence[$user]['cloture'] == 0) {
					// Gestion des pauses
					if ($activite == 'I') {
						$dureePause += $dureeEcart_2;
						// *************** ENVOIS D'UN SOS AUTOMATIQUE ***************
						if ($dureePause / 60 > $timingSOS && (time() - $timeCourante) < 60) {
							mg::message($destinatairesSOS, "SOS AUTOMATIQUE de $user, Aucun mouvement depuis plus de " . $timingSOS/60 . " mn, Coordonnées ($latlng). VOIR :  https://georgein.dns2.jeedom.com/mg/util/geofence.html");
						}
						// ***********************************************************
						// Fin de pause
						if ($dureePause > $pauseMin && $activite_2 == 'E') {
							$tabGeofence[$user]['sommePause'] += $dureePause;
						}
					} else { $dureePause = 0; }

					$tabGeofence[$user]['dureeTotale'] += $dureeEcart;
					$tabGeofence[$user]['distanceTotale'] += $ecart;

					// Traitement delta altitude
					$altitude = ($lastAltitude != 0) ? $lastAltitude : $oldAltitude;
					$ecartAltitude = ($oldAltitude != 0) ? ($altitude - $oldAltitude) : 0;
					if ($ecartAltitude > 0) { $tabGeofence[$user]['denivelePlus'] = $tabGeofence[$user]['denivelePlus'] +  + $ecartAltitude * $coeffDeniveles; }
					if ($ecartAltitude < 0) { $tabGeofence[$user]['deniveleMoins'] = $tabGeofence[$user]['deniveleMoins'] +	$ecartAltitude * $coeffDeniveles; }
				}
			}
		// Cloture entrainement
		} else {
			// Cloture
			if ($tabGeofence[$user]['debTime'] > 0 && $tabGeofence[$user]['cloture'] == 0) {
				$tabGeofence[$user]['cloture'] = $timeCourante;
			}

		}

		// Suppression Entrainements précédents si trop court (< 1 km ou 15 mn)
		if ($tabGeofence[$user]['cloture'] > 0 && $timeCourante >= $tabGeofence[$user]['cloture'] && ($tabGeofence[$user]['distanceTotale'] < 1 || $tabGeofence[$user]['dureeTotale'] < 900)) {
			mg::message('', "************* Suppression entrainement trop court de $user : " . $result[$i]['datetime'] . " - Distance : " . $tabGeofence[$user]['distanceTotale']);
			$tabGeofence[$user]['debTime'] = 0;
			$tabGeofence[$user]['cloture'] = 0;
			$tabGeofence[$user]['dureeTotale'] = 0;
			$tabGeofence[$user]['sommePause'] = 0;
			$tabGeofence[$user]['$dureePause'] = 0;
			$tabGeofence[$user]['distanceTotale'] = 0;
			$tabGeofence[$user]['denivelePlus'] = 0;
			$tabGeofence[$user]['deniveleMoins'] = 0;
		}

		// Enregistrement pour affichage
		$tabGeofence[$user]['latlngs'] .= "[$latlng, $timeCourante],";
		$tabGeofence[$user]['complements'] .= "[$ecart, $dureeEcart, $altitude, '$activite', $pcBatterie],";
		$oldlatlng = $latlng;
		$oldAltitude = $altitude;
	} // fin for

	// Pour éviter erreurs d'index inexistant
	$tabGeofence[$user]['complements'] .= "[0, 0, $altitude, '', 0],";
	$tabGeofence[$user]['complements'] .= "[0, 0, $altitude, '', 0],";
	$tabGeofence[$user]['complements'] .= "[0, 0, $altitude, '', 0],";

	// *********************************** CALCUL DE LA SYNTHESE DE L'ENTRAINEMENT ************************************
	if ($tabGeofence[$user]['debTime'] != 0) {
	$tabGeofence[$user]['dureeTotale'] = $tabGeofence[$user]['dureeTotale'];

		$tabGeofence[$user]['dureeMouvement'] = $tabGeofence[$user]['dureeTotale'] - $tabGeofence[$user]['sommePause'];
		if ($tabGeofence[$user]['distanceTotale'] == 0 || $tabGeofence[$user]['dureeMouvement'] == 0) {$tabGeofence[$user]['vitesseMoyenne'] = 0; }
		else { $tabGeofence[$user]['vitesseMoyenne'] = $tabGeofence[$user]['distanceTotale'] / $tabGeofence[$user]['dureeMouvement'] * 3600; }
	}

	// Calcul des lastValues
	$last = explode(',', mg::getCmd($id, '', $lastTime)); // Decomposition de lastValue
	$tabGeofence[$user]['lastTime'] = $lastTime;
	$tabGeofence[$user]['lastPcBatterie'] = intval($last[3]);
	$tabGeofence[$user]['lastSSID'] = $last[4];

	return;
}

?>