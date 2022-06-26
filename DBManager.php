<!--
ToDo: last update 6/11/22

1. security check for user input data
    - sanitization:
        -strip_tags
        -htmlentities, slices
        -stripslashes
    - SQL safety check: place holder stmt

2. if onSubmitAdd return false, is submision cancelled? Seems not.

3. read 'programming php'

4. understand when 'session_id' changes
    - by different browser? / machine? / ip? / etc


6/11
    - reload problem resolved -> header("Location: index.php");
    - login attempt counter
    - removed "DBManager.php" from the client. Using "index.php" only
    - refactored significantly
    - debug: $_SESSION[xxx] = yyy  <-> unset($_SESSION[xxx])   vs $_SESSION[xxx] = 0
    - debug: $conn = DBManager::getConnection(); does not throw error when failed in PHP 7.4 @ wsl
-->
<?php
class DBMgr{
    private $_url;
    private $_db;
    private $_table;
    private $_user;
    private $_pwd;

    private function __construct($url, $db, $table, $user, $pwd){
        $this->_url = $url;
        $this->_db = $db;
        $this->_table = $table;
        $this->_user = $user;
        $this->_pwd = $pwd;
    }
    public function connect(){ return new mysqli($this->_url,$this->_user,$this->_pwd,$this->_db);}
    static function startSession(){ session_start();}
    static function onLogin(){
        $url = "3.82.20.219";    //AWS_U_22.04_0
        //$url = "localhost";    //Windows -> Windows
        //$url = "172.30.112.1"; //WSL -> Windows
        $_SESSION[session_id()] = new DBMgr($url, "test", "users", $_POST['user_name'], $_POST['user_password']);

        try{
            $conn = DBMgr::getConnection();
            if (!$conn->connect_error){
                $conn->close();
                return 1;
            }
            else throw new Exception();
        }
        catch(Exception $err){
            unset($_SESSION[session_id()]);
            if (isset($_SESSION["loginCnt"])) $_SESSION["loginCnt"]++;
            else $_SESSION["loginCnt"] = 1;
            return 0;
        }
    }
    static function onLogout(){
        $_SESSION = array(); //== unset($_SESSION)
        session_destroy(); // release other file resources, etc
    }
    static function isLogin(){ return isset($_SESSION[session_id()])? 1:0;}
    static function getLoginFailCnt(){ return isset($_SESSION["loginCnt"])? $_SESSION["loginCnt"]:0;}
    static function getConnection(){ return $_SESSION[session_id()]->connect();}
    static function getUserName(){ return $_SESSION[session_id()]->_user;}
    static function getTable(){ return $_SESSION[session_id()]->_table;}

    static function addItem(){
        $conn = DBMgr::getConnection();
        if ($conn->connect_error) echo "error connecting DB";

        $columns = array_keys($conn->query("SELECT * FROM ".DBMgr::getTable(). " LIMIT 1")->fetch_assoc());
        $inputArray = array();

        for ($i = 1; $i < count($columns); $i++)
            $inputArray[$i] = $_POST["add_".$columns[$i]]? "'".$_POST["add_".$columns[$i]]."'":"NULL";

        $qAdd="INSERT INTO ".DBMgr::getTable()."(";
        for ($i = 1; $i < count($columns); $i++)
            $qAdd .= $columns[$i].($i < (count($columns) -1 )? ',': '');
        $qAdd .= ") VALUES(";

        for ($i = 1; $i < count($columns); $i++)
            $qAdd .= $inputArray[$i].($i < (count($columns) -1 )? ',': '');
        $qAdd .= ")";

        $conn->query($qAdd);
        /*
        try{ $conn->query($qAdd);}
        catch(Exception $er){
            //invalid attempt to add data;
            echo ("error in addItem");
        }
        */
        $conn->close();
        return 1;
    }
    static function deleteItem(){
        $conn = DBMgr::getConnection();
        if ($conn->connect_error) echo "error connecting DB";

        $fieldName = $conn->query('DESC '.DBMgr::getTable())->fetch_array(MYSQLI_NUM)[0];
        $primaryKey = $_POST['person_id']? (int)$_POST['person_id'] : '-1';
        $qIn = 'DELETE FROM '.DBMgr::getTable().' WHERE '.$fieldName.' = '.$primaryKey;

        $conn->query($qIn);
        $conn->close();
        return 1;
    }
    static function renderDashboard(){
        $res = DBMgr::renderWelcome();
        $res .= DBMgr::renderLogoutForm();
        $res .= DBMgr::renderAddForm();
        $res .= DBMgr::renderTable();
        DBMgr::writeHTML($res);
    }
    static function renderTable(){
        $conn = DBMgr::getConnection(); 
        if ($conn->connect_error)echo "error connecting DB";

        $qIn = "SELECT * FROM ".DBMgr::getTable();
        $qOut = $conn->query($qIn);
        if (!$qOut) echo "query error";

        $colSize = $qOut->field_count;
        $res = "<table border = '1' cellpadding='2' cellspacing='2'>
                <tr><th colspan='".$colSize."'><h2>".DBMgr::getTable()."</h2></th></tr>";

        $columns = array_keys($qOut->fetch_assoc()); //very useful function : key name -> array of strings
        $res .= "<tr>";
        $res .= "<th>"." "."</th>"; // no item for first column (person_id not shown)
        for ($i = 1; $i < $colSize; $i++)
            $res .= "<th>".$columns[$i]."</th>";
        $res .= "</tr>";
        $qOut->data_seek(0);

        for ($i = 0; $i < $qOut->num_rows; $i++){ // rows
            $row = $qOut->fetch_array(MYSQLI_NUM);
            $res .= "<tr>";
            $res .= //del button
            "<td><form action='index.php' method='post'>
                    <input type='hidden' name = 'person_id' value = '".$row[0]."' >". //value here == identifier
                "<input type='submit' value='Del' name='DBM_submit'>     </form></td>";
            for ($j = 1; $j < $qOut->field_count ; $j++) //columns
                $res .= "<td>".($row[$j]? htmlspecialchars($row[$j]): "")."</td>"; //
            $res .= "</tr>";
        }
        $res .= "</table>";
        $qOut->close();
        $conn->close();
        return $res;
    }
    static function renderAddForm(){
        $conn = DBMgr::getConnection();
        if ($conn->connect_error) echo "error connecting DB";

        $columns = array_keys($conn->query("SELECT * FROM ".DBMgr::getTable(). " LIMIT 1")->fetch_assoc());
        $conn->close();

        $arg = "";
        $tableRows = "";
        for($i = 1; $i < count($columns); $i++){
            $tableRows .= '<tr><td>'.$columns[$i]."</td><td><input type='text' name='add_".$columns[$i]."'></td></tr>";
            $arg .="'add_".$columns[$i]."',";
        }
        $addHTML =
        '<br><br><table style="border: 2px solid #999999" border="0" cellpadding="2" cellspacing="5" bgcolor="#eeeeee">
        <th colspan="2" align="center"> Add Data </th>
        <form action="index.php" method="post" onsubmit="onSubmitAdd('.substr($arg, 0, -1).')">';
        $addHTML .=$tableRows;
        $addHTML .= "<tr><td colspan='2' align='center'><input type='submit' name='DBM_submit' value='Add'></td></tr> </form></table><br>";
        return $addHTML;
    }
    static function renderLoginForm(){
        $failCnt = DBMgr::getLoginFailCnt();
        $res =
        '<table style="color:blue; font: normal 14px Sans-serif; border: 1px solid #999999" border="0" cellpadding="2" cellspacing="5" bgcolor="#ffffbf">
        <th colspan="2" align="center"> DB Manager </th>
        <form action="index.php" method="post" autocomplete="on" required="required" onsubmit="onSubmitLogin()">
            <tr><td>ID </td><td><input type="text" name="user_name" value="name.."></td></tr>
            <tr><td>Password </td><td><input type="password" name="user_password"></td></tr>
            <tr><td colspan="2" align="center"><input type="submit" name="DBM_submit" value="Login"></td></tr>
        </form>
        </table>'.(($failCnt>0)? "<br> Login failed ".$failCnt." times":"");
        DBMgr::writeHTML($res);
    }
    static function renderLogoutForm(){
        return
        '<form action = "index.php" method="post" id="id_logoutForm"></form>
        <input type="submit" name="DBM_submit" value="Logout" form="id_logoutForm">';
    }
    static function renderWelcome(){return 'Welcome ['.DBMgr::getUserName().'] to DB Manager';}
    static function writeHTML($str){
        echo <<<_TAG
        <!DOCTYPE html>
        <html>
            <head>
                <title>-PHP-</title>
            </head>
            <body>
                $str
                <script type='text/javascript' src='js.js'></script>
            </body>
            </html>
        _TAG;
    }
}
DBMgr::startSession();
//DBMgr::onLogout(); exit; //for debugging

switch($_SERVER['REQUEST_METHOD']){
    case 'GET':
        if (DBMgr::isLogin()) DBMgr::renderDashboard();
        else DBMgr::renderLoginForm();
        break;

    case 'POST':
        switch($_POST['DBM_submit']){
            case 'Login':   DBMgr::onLogin();       break;
            case 'Logout':  DBMgr::onLogout();      break;
            case 'Del':     DBMgr::deleteItem();    break;
            case 'Add':     DBMgr::addItem();       break;
        }
        header("Location: index.php");
        break;
}
?>