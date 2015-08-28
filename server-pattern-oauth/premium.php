<?php

session_start();

$currentUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";

if(empty($_SESSION['logged_in_user']) || empty($_SESSION['configuration'])){
    
    header ('Location: ' . "test.php");
    
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type">
    <meta content="initial-scale=1, maximum-scale=1,user-scalable=no" name=
    "viewport">

    <title>Premium</title>
        <link href="//js.arcgis.com/3.14/dijit/themes/claro/claro.css" rel="stylesheet">
    <link href="//js.arcgis.com/3.14/esri/css/esri.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>

      #meta {
        position: absolute;
        left: 20px;
        bottom: 20px;
        width: 20em;
        height: 16em;
        z-index: 40;
        background: #ffffff;
        color: #777777;
        padding: 5px;
        border: 2px solid #666666;
        -webkit-border-radius: 5px;
        -moz-border-radius: 5px;
        border-radius: 5px;
        font-family: arial;
        font-size: 0.9em;
      }

      #meta h3 {
        color: #666666;
        font-size: 1.1em;
        padding: 0px;
        margin: 0px;
        display: inline-block;
      }
      
    #legendWrapper {
         padding: 20px 0 0 0;
      }
      
      #feedback {
         position: absolute;
         height: 410px;
         font-family: arial;
         margin: 5px;
         padding: 10px;
         z-index: 40;
         background: #fff;
         color: #444;
         width: 300px;
         right: 15px;
         top: 100px;
         box-shadow: 0 0 5px #888;
      }
      
    </style>
    <script src="//js.arcgis.com/3.14/"></script>
    <script>
    var config;
    config = JSON.parse('<?php echo $_SESSION['configuration']; ?>');

    console.log(config);

    </script>
    <script>
  var map, featureLayer, oAuthInfo, legend;
      require([
        "dojo/dom-construct",
        "dojo/dom",
        "esri/dijit/Legend",
        "dojo/_base/lang",
        "esri/request",
        "esri/arcgis/OAuthInfo", 
        "esri/IdentityManager", 
        "esri/map", 
        "esri/layers/FeatureLayer",
        "esri/renderers/SimpleRenderer", "esri/Color", "esri/symbols/SimpleLineSymbol", "esri/symbols/SimpleFillSymbol",
        "esri/renderers/UniqueValueRenderer",
        "dojo/parser", "esri/renderers/smartMapping","dojo/on","dojo/domReady!"
      ], function(
    	      domConstruct,
    	      dom,
    		  Legend,
    		    lang,
    	        esriRequest,
            OAuthInfo, 
            esriId, 
            Map, 
            FeatureLayer,
            SimpleRenderer, Color, 
        SimpleLineSymbol, SimpleFillSymbol,
        UniqueValueRenderer,
        parser, smartMapping, on
      ) {
        parser.parse();

        oAuthInfo = new OAuthInfo({appId: "bn2zfH74dj0Vjo30"});
        esriId.registerOAuthInfos([oAuthInfo]);
        refreshToken();

        var signoutBtn = dom.byId("signout");
        	if(signoutBtn){
            	on(signoutBtn, "click", function(){
            		document.location = "index.php?signout=true";
            	});
        }
           
        updateClientConfiguration();

        map = new Map("map", {
          basemap: "gray",
          center: [-95.625, 39.243],
          zoom: 4,
          slider: true
        });
        
        map.on("load", addFeatureLayer);
        
        map.on("layers-add-result", function (evt) {
                console.log("here");    
                createRenderer();
        });

        setInterval(function(){ refreshToken(); }, 1500000); //Refresh token every 25 minutes

        function updateClientConfiguration(){
            esriId.registerToken(config);
            console.log("Updated client config");
        }

        function refreshToken(){
            var requestHandle = esriRequest({
                "url": "index.php?refresh=true"
            });
            
           requestHandle.then(function(response){
                config = JSON.parse(response);
                lang.hitch(this, updateClientConfiguration(response));
      
            }, function(error){
                console.log(error)
            });
        }
        
        function addFeatureLayer() {
          featureLayer = new FeatureLayer("http://demographics4.arcgis.com/arcgis/rest/services/USA_Demographics_and_Boundaries_2014/MapServer/25", {
            mode: FeatureLayer.MODE_ONDEMAND,
            outFields: ["OBJECTID","ST_ABBREV", "MEDHINC_CY"]
          });

          var renderer = new SimpleRenderer(new SimpleFillSymbol().setOutline(new SimpleLineSymbol().setWidth(0.5)));
          renderer.setColorInfo({
            field: "MEDHINC_CY",
            minDataValue: 20000,
            maxDataValue: 150000,
            colors: [
              new Color([204, 255, 153]),
              new Color([102, 204, 0])
            ]
          });
          //featureLayer.setRenderer(renderer);

          map.addLayers([featureLayer]);
        }

        function createRenderer() {
            //smart mapping functionality begins
            smartMapping.createClassedColorRenderer({
               layer: featureLayer,
               field: "MEDHINC_CY",
               basemap: map.getBasemap(),
               classificationMethod: "quantile"
            }).then(function (response) {
               featureLayer.setRenderer(response.renderer);
               featureLayer.redraw();
               createLegend(map, featureLayer);
            });
         }
        

        //Create a legend
        function createLegend(map, layer) {
           //If applicable, destroy previous legend
           if (legend) {
              legend.destroy();
              domConstruct.destroy(dom.byId("legendDiv"));
           }

          // create a new div for the legend
           var legendDiv = domConstruct.create("div", {
              id: "legendDiv"
           }, dom.byId("legendWrapper"));

           legend = new Legend({
              map: map,
              layerInfos: [{
                 layer: layer,
                 title: "Median Household Income Data"
           }]
           }, legendDiv);
           legend.startup();
        }
        
      });
    </script>
</head>

<body class="claro">
    <div class="top-nav">
        <div id="logo">
            <span class="title" id="home">ArcGIS Platform</span> <span class=
            "slug">- Identity Management</span>
        </div>

        <div id="tools">
            <ul>
                <li>
                <?php echo "Hi " . $_SESSION['logged_in_user']['username'] . ":" ?></li>

                <li id="signout">Sign out</li>
            </ul>
        </div>
    </div>

    <div class="content-area-map">
        <div id="map"></div>
        <div id="feedback">
        <div id="legendWrapper"></div>
        </div>

        <div id="meta">
            <h3>Premium Content Available When Logged-In</h3><br>
            <br>
            This sample delivers premium content from Esri's Demographic
            Service. The costs assocated with service usage are deducted from
            the users account (the user using the service) instead of being
            deducted from the developer's account.
        </div>
    </div>
</body>
</html>