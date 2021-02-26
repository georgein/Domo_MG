<?php

/********************************************* EDITEUR DE FICHIER ENBEDDED DANS UN WIDGET *****************************
*	Principe : 	La variable 'FileEdit' contient le contenu du fichier édité.
				La variable FileEditName contient le nom du fichier en cours d'édition.
				La commande info porteuse du widget est initialisé avec la variable 'FileEdit'.
				Le HTML du widget est une FORM POST qui appelle 'WI_EditFile.php'.
				'EditFile.php'
					Lors du 'Charger'  : Enregistrement de la variable 'FileEditName' et appel du scénario FileEdit2 qui lira le fichier et le mettra dans la variable 'FileEdit'.
					Lors du 'Enregistrer' : Il enregistre le contenu de la variable 'EditFile' dans le fichier 'EditFileName'.
					Lors du 'Quitter' il destruction les variables 'EdiFile' et 'EditFileName'
/*---------------------------------------------------------------------------------------------------------------------
/*---------------------------------------------------------------------------------------------------------------------

<!-- ********************************************** CODE HTML DU WIDGET ******************************************* -->
<!-- DEBUT WIDGET EDIT FILE -->
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="robots" content="noindex, nofollow">
		<title>Widget édition de texte Jeedom</title>

	<style>
	TEXTAREA {
		font-size: #fontSize#;
		width: #widthText#;
		}
	</style>

	  </head>
	<body>
		<FORM method=post action="./mg/WI_EditFile.php">
		  <div>
			<input style=body type="text" value="#EditFile#" name="EditFileName" size="75">
			  </div>
			<br>
			  <div>
			<input	type="submit" name="submit" value="Quitter">
			<input	type="submit" name="submit" value="Charger">
			<input	type="submit" name="submit" value="Enregistrer">
			  </div>
			<br>
			  <div>
			<TEXTAREA name="message" rows="#rows#" cols="#cols#">
				#state#
			</TEXTAREA>
		 </div>
		</FORM>
	</body>
<!-- FIN WIDGET EDIT FILE -->
</html>

<!-- ****************************************FIN DU CODE HTML DU WIDGET ******************************************* -->

---------------------------------------------------------------------------------------------------------------------*/
/*---------------------------------------------------------------------------------------------------------------------

*****************************************   CODE PHP DU SCENARIO APPELE ***********************************************
<?php
include_once getRootPath() . '/mg/mg.class.php'; mg::init();

// Récupère le fichier ini à éditer
mg::setVar('_EditFile', shell_exec("sudo cat " . mg::getVar('_EditFileName')));
?>
**************************************** Fin du code du scénario appelé ***********************************************

---------------------------------------------------------------------------------------------------------------------*/
/*---------------------------------------------------------------------------------------------------------------------

*****************************************   CODE PHP APPELE PAR LE FORM HTML *****************************************/
?>
<!-- RETOUR A LA PAGE PRECEDENTE IMMEDIATEMENT -->
<meta http-equiv="refresh" content="1;<?php echo $_SERVER['HTTP_REFERER']; ?>" />

<?php
	$apikey = 'w35cb9cmgg2ehbsbca1h';
	$lngSegments = 2000; // Longueur maximum transmissible via l'API Jeedom
	$ScenEditFile = 173;

	$submit = trim(htmlspecialchars($_POST['submit']));
	$nomTab = trim(htmlspecialchars($_POST['nomTab']));
	$nomTabOrg = trim(htmlspecialchars($_POST['nomTabOrg']));
	$message = trim($_POST['message']).trim($_POST['message2']);
	
	$lngMessage = strlen($message);
	// ENREGISTRER
	if ($submit == 'Enregistrer') {
		echo ("( Nombre de variable du _POST " . count($_POST) . " - action='$Action' nomTab='$nomTab' nomTabOrg='$nomTabOrg' nbSegments='$nbSegments' lngMessage='$lngMessage' )<br>");
		if ($nomTabOrg != '') { $nomTab = $nomTabOrg; } // Sécurité pour éviter d'enregistrer sous un autre nom
		if ($lngMessage > 0) {
			$nbSegments = min(20, round(($lngMessage / $lngSegments) +0.5));
			$tabMessage = str_split($message, $lngSegments);
			for ($i=0; $i<$nbSegments; $i++) {
				API_Var($apikey, "retFile$i", trim($tabMessage[$i], '"'));
			}
		} else {
			echo ("*************** MESSAGE VIDE ENREGISTREMENT ANNULEE !!! ***************");
			return;
		}
	}
	
	// LANCEMENT SCENARIO DE GENERATION DU TABLEAU
	API_Scenario($apikey, $ScenEditFile, 'start', "submit='$submit' nomTab='$nomTab' nbSegments='$i' lngMessage='$lngMessage'");

// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// Lecture et Enregistrement d'une variable en BdD (selon la présence ou non de '$Value'
function API_Var($apikey, $Name, $Value) {
		//echo "<br>-- Lecture/Enregistrement/Lecture de la variable '$Name' --<br>$Value<br>";
		$url = "192.168.2.30/core/api/jeeApi.php";
		$params = array(	'apikey'=>$apikey,
							'type'=>'variable',
							'name'=>$Name,
							'value'=>$Value);
		$url = "http://$url?" . http_build_query($params);
		file_get_contents($url);
//		echo $url;
	}
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// Lancement d'un scénario
function API_Scenario($apikey, $ID, $Action='start', $tag='') {
		echo "<br>-- $Action du scénario N° '$ID' --<br>";
		$url = "192.168.2.30/core/api/jeeApi.php";
		$params = array(	'apikey'=>$apikey,
							'type'=>'scenario',
							'id'=>$ID,
							'action'=>$Action,
							'tags'=>$tag
							);
		$url = "http://$url?" . http_build_query($params);
		$Value = trim(file_get_contents($url));
	}
?>
