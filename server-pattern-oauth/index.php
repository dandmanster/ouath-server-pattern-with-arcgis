<?php

$client_id = "bn2zfH74dj0Vjo30";

/*
 * If this app is designed to work with ArcGIS on-premises
 * upated the $portal_sharing_rest variable below to something 
 * like this https://<host>:<port>/<subdirectory>/sharing/rest
 
 */

$portal_sharing_rest = "https://www.arcgis.com/sharing/rest";

$code = isset($_GET['code']) ? $_GET['code'] : null;

$signout = isset($_GET['signout']) ? $_GET['signout'] : null;

$saas_username = isset($_POST['username']) ? $_POST['username'] : null;

$saas_password = isset($_POST['password']) ? $_POST['password'] : null;

$refresh = isset($_GET['refresh']) ? $_GET['refresh'] : null;


session_start();

if(empty($_SESSION['configuration'])){
    
    $_SESSION['configuration'] = json_encode(null);
    
}

function post($params, $url){

    try {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);

    } catch (Exception $e) {

        echo "error";

        error_log($e->getMessage(), 0);

    }

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $body = substr($response, $header_size);

    $json = json_decode($body, true);
    
    return $json;
}

function updateClientConfiguration($tokenInfo){
    
    /* This method packages up the token inside what we call a client configuration.
     * We package up a few other things too (in addition to the access token) to help 
     * with integration into the ArcGIS API for Javascript.
     */
    
    global $configuration;
    
    global $portal_sharing_rest;
    
    $expires = ($tokenInfo['expires_in'] * 1000) + (time() * 1000); //Time in the future when the token will expire.
    
    $configuration = array(
        'token' => $tokenInfo['access_token'],
        'expires' => $expires - 5,
        'server' => $portal_sharing_rest,
        'userId' => $tokenInfo['username'],
        'ssl' => true
    );
    
    /*
     * Here we actually set the configuration to a specific JSON string, which
     * when the page loads will be transformed into a JavaScript Object 
     * and injected inside a script tag 
     */
    
    $configuration = json_encode($configuration);
    
    $_SESSION['configuration'] = $configuration;
}

function connectAccount($uid,$account,$name,$grant_type,$refresh_token){
    
    /*
     * This method would typically update a database table.  For simplicity 
     * we just update a CSV file that lives on disk.  This file needs to be
     * writable.  The info in the CSV should contain the needed values to refresh
     * an OAuth token. 
     */
    
    global $client_id;
    
    $user = $_SESSION['logged_in_user'];
    
    $existing_connection = fetchConnectedAccounts($user['uid'], "arcgis");
    
    //print_r($existing_connection);
    
    $connections = array_map('str_getcsv', file('connected_oauth_accounts.csv'));
    
    $user_connection_to_add = array($uid,$account,$name,$grant_type,$refresh_token, $client_id);
    
    if(isset($existing_connection)){
        
        $file = fopen("connected_oauth_accounts.csv","w");
        
        fputcsv($file, array("id","vendor","name","grant_type","refresh_token","client_id"));
        
        foreach($connections as $key =>$connection){
            
            if($connection[0] == $existing_connection[0] && $connection[1] == $existing_connection[1]){

                continue; //When updating the csv avoid creating duplicate connections

            }else if($connection[0] == "id" && $connection[1] == "vendor"){
                
                continue; //Avoid removing field names located on the first line in the CSV
   
            }else{
                
                fputcsv($file, $connection);
            }
        }
        
        fputcsv($file, $user_connection_to_add);

    }else{
        
        $file = fopen("connected_oauth_accounts.csv","a");
        
        fputcsv($file, $user_connection_to_add);
        
    }
    
    fclose($file);
    
}

function fetchConnectedAccounts($uid, $vendor) {
    
    /**
     * Get a list of OAuth vendors which the user is connected too.  Typically, 
     * this list would be pulled from a database, however to be simple we query 
     * a CSV file.  When creating your data structure which you will search
     *  make sure you include the data needed to refresh the users ArcGIS token.  
     *  Specifically you need two things, the grant type and the refresh token.  
     *  In the CSV file these values come back in position 3 and position 4 of the array.
     **/
    
    $csv = array_map('str_getcsv', file('connected_oauth_accounts.csv'));
    
    /*Search the connections looking for any arcgis account assocted with the user*/
    
    foreach($csv as $key => $record){
        
        if($record[0] == $uid && $record[1] == $vendor){
            
            /*Great an arcgis connection has been found for the logged in saas user.
             * Now, let's sign that user into arcgis so they don't have to recall
             * their ArcGIS username and password
             */
            
            break;
            
        }
   
    }
    
    return $record;
}

if($code){

    $params = array(
        'client_id' => $client_id,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => strtok('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}", '?')
    );
    
    /*
     * Per the OAuth Server spec we need to send the OAuth code back to the 
     * OAuth provider to get back an access token.  Below we make a post and 
     * then make use of the response. 
     */
    
    $response = post($params, $portal_sharing_rest . "/oauth2/token/");
    
    $tokenInfo = array(
        'access_token' => $response['access_token'], // this is our access token!
        'expires_in' => $response['expires_in'], 
        'refresh_token' => $response['refresh_token'], 
        'username' => $response['username']
    );

    /*
     * Next get the SaaS user our of the session so we can assocate the SaaS user with
     * the ArcGIS connection and ArcGIS identity.
     */
    
    $user = $_SESSION['logged_in_user'];
    
    /*
     * Connect the SaaS user with their ArcGIS identity.  The connection is saved
     * in a file called connected_oauth_accounts.csv. Normally this would be stored
     * in some kind of database table.  Specifically we save the needed 
     * info so we can recall the connection and refresh the ArcGIS token when needed.
     */
    
    connectAccount($user['uid'],"arcgis",$tokenInfo['username'],"refresh_token",$tokenInfo['refresh_token'],$client_id);
    
    updateClientConfiguration($tokenInfo);
    
    header ('Location: ' . strtok('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}", '?'));
    
}else if(isset($saas_username) && isset($saas_password)){
    
    
    /* This array represents a user store, like a user table inside a database.
     * Keep in mind this is over simplified. Credentials should not be stored in plain text.  */
    
    $users = array(
        array("uid" => 27, "username" => "ArcGIS Developer", "password" => "password"),
        array("uid" => 28, "username" => "Kurt", "password" => "password"),
        array("uid" => 29, "username" => "Katie", "password" => "passwordABC"),
        array("uid" => 29, "username" => "Myles", "password" => "password123")
    );
    
    foreach($users as $key => $user){
        
        if($user["username"] == $saas_username && $user["password"] == $saas_password){
            
            $_SESSION['logged_in_user'] = $user;
            
            $connection = fetchConnectedAccounts($user['uid'], "arcgis");
            
            /*
             * If there are no ArcGIS accounts connected to this user
             * then return
             */
            
            if(empty($connection)){
                
                break;
            }
            
            /*
             * Since the a connection exists take the needed values out of the connection to refresh the token.  Specifically
             * we need the client_id, refresh_token and grant_type to generate a fresh token.
             */
            
            $params = array(
                'client_id' => $connection[5],
                'refresh_token' => $connection[4],
                'grant_type' => $connection[3]
            );
            
            $refreshedTokenInfo = post($params, $portal_sharing_rest . "/oauth2/token/");
            
            /*
             * Once the refreshed token information comes back send that 
             * to the client configuration.
             */
            
            $tokenInfo = array(
                'access_token' => $refreshedTokenInfo['access_token'],
                'expires_in' => $refreshedTokenInfo['expires_in'],
                'username' => $refreshedTokenInfo['username']
            );
            
            updateClientConfiguration($tokenInfo);
    
            break;
            
        }
    }
    
    /* Reload page to set the credentials in the client
     *  and prevent form resubmission prompts */
    
    header ('Location: ' . $_SERVER['REQUEST_URI']);
    
    exit();
    
}else if(isset($signout)){
    
    echo $_SERVER['REQUEST_URI'];
    
    session_destroy();
    
    //exit();
    
    header ('Location: ' . strtok('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}", '?'));
    
}else if(isset($refresh)){
    
    if($_SESSION['logged_in_user']){
        
        $user = $_SESSION['logged_in_user'];
        
        $connection = fetchConnectedAccounts($user['uid'], "arcgis");
        
        /*
         * Since the a connection exists take the needed values out of the connection to refresh the token.  Specifically
         * we need the client_id, refresh_token and grant_type to generate a fresh token.
         */
        
        $params = array(
            'client_id' => $connection[5],
            'refresh_token' => $connection[4],
            'grant_type' => $connection[3]
        );
        
        $refreshedTokenInfo = post($params, $portal_sharing_rest . "/oauth2/token/");
        
        /*
         * Once the refreshed token information comes back send that
         * to the client configuration.
        */
        
        $tokenInfo = array(
            'access_token' => $refreshedTokenInfo['access_token'],
            'expires_in' => $refreshedTokenInfo['expires_in'],
            'username' => $refreshedTokenInfo['username']
        );
        
        updateClientConfiguration($tokenInfo);
        
        $configuration = $_SESSION['configuration'];
        
        header('Content-Type: application/json');
        
        echo json_encode($configuration);
        
        exit();
              
    }else{
        
        header ('Location: ' . "index.php");
        
        exit();
    }
    
}


?>

<!DOCTYPE html>

<html>

<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="initial-scale=1, maximum-scale=1, user-scalable=no">

<title>Identity Management</title>

<link rel="stylesheet" href="css/style.css">

<script>

var config = JSON.parse('<?php echo $_SESSION['configuration']; ?>');

console.log(config);

</script>



<script src="http://js.arcgis.com/3.14/"></script>

<script>
require([ "dojo/dom","esri/map", "dojo/dom-class", "dojo/on"], function(
		dom, Map, domClass, on) {


	var signinBtn = dom.byId("sign-in");
	var esriOauthBtn = dom.byId("esri-oauth");
	var connectionsBtn = dom.byId("connections");
	var signoutBtn = dom.byId("signout");
	var premiumBtn = dom.byId("premium-content");
	

	if(signinBtn){
        	on(signinBtn, "click", function(evt){
        		domClass.toggle("signin-area", "signin-area-on");
        	});
	}
	if(esriOauthBtn){
        	on(esriOauthBtn, "click", function(){
        		document.location = "https://www.arcgis.com/sharing/oauth2/authorize?client_id=bn2zfH74dj0Vjo30&response_type=code&expiration=-1&redirect_uri=http://localhost/work/dev/identity-management/server-pattern-demo/index.php";
        		  });
	}
	if(connectionsBtn){
    	on(connectionsBtn, "click", function(evt){
    		domClass.toggle("connections-area", "connections-on");
    	});
    	if(signoutBtn){
        	on(signoutBtn, "click", function(){
        		document.location = window.location.href +"?signout=true";
        		  });
	}
    	if(premiumBtn){
        	on(premiumBtn, "click", function(){
        		document.location = "premium.php";
        		  });
	}
}
    
});
</script>

</head>

<body>

<div class="top-nav">
<div id="logo"><span id="home" class="title">ArcGIS Platform </span> <span class="slug">- Identity Management</span></div>




<?php if(isset($_SESSION['logged_in_user'])): ?>

<div id="tools">

<ul>

<li><?php echo "Hi " . $_SESSION['logged_in_user']['username'] . ":" ?></li>

<li id="connections">Connections</li>

<li id="premium-content">Premium Content</li>

<li id="signout">Sign out</li>

</ul>

</div>

<?php else: ?>

<div id="tools"><ul><li id="sign-in">Sign-in</li></ul></div>

<?php endif; ?>


</div>
<!-- <a id="signin" href="https://www.arcgis.com/sharing/oauth2/authorize?client_id=Y7TgHMgjwgjOYwbp&response_type=code&expiration=180&redirect_uri=https://localhost/work/dev/oauth-basic/php/receiver.php">Sign In With ArcGIS Online</a> -->
<div class="content-area">






<?php 

if(empty($_SESSION['logged_in_user'])): 

?>
<div id="signin-area" class="signin-area">
<div class="wrapper">
<div class="box">
<h1>Welcome</h1>

<form class="form" method="post">
<input type="text" name="username" placeholder="Username">
<input type="password" name="password" placeholder="Password">
<button type="submit" id="login-button">Login</button>
</form>
</div>
</div>
</div>
<?php endif; ?>




<div id="connections-area" class="connections">
<div class="wrapper">
<div class="box">
<h1>Connections</h1>

<div>
    <div class="connected-vendors">
        <div>
            <img id="esri-oauth" src="images/esri.jpeg" width="150" height="150"/>
        <div class="checkbox-container">
            <input id="esri-oauth-checkbox" type="checkbox" width="20" height="20" name="esri" value="esri"/>
        </div>
        
        </div>
    </div>
    <div class="connected-vendors">
        <div>
            <img src="images/twitter.png" width="150" height="150"/>
        <div class="checkbox-container">
            <input type="checkbox" checked name="esri" value="mapillary"/>
        </div>
    </div>
        
    </div>
</div>
</div>
</div>
</div>


</div>
</body>
</html>