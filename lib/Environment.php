<?php
class Environment
{
  public $name,$target,$scm;
  private $config,$shell,$current_role,$mysql;

  public function __construct($env_name,$args=null)
  {
    $this->deploy_to = "./";
    $this->name = $env_name;
    $this->config = (array) $args;
    $this->init_scm();
  }

  public function __get($prop)
  {
    if( array_key_exists($prop, $this->config))
      return $this->config[$prop];
    return null;
  }

  public function __isset($prop)
  {
    return isset($this->config[$prop]);
  }

  public function __set($prop,$value)
  {
    $this->config[$prop] = $value;
  }

  public function role($key)
  {
    if(!$this->$key) return false;
    if( !$this->current_role )
    {
      $this->current_role = $this->$key;
      return $this->update_target($this->current_role[0]);
    }else
    {
      $index = array_search($this->target,$this->current_role);
      if( isset($this->current_role[$index+1]))
      {
        return $this->update_target($this->current_role[$index+1]);
      }
      else
        $this->current_role = false;
    }
  }

  public function next_role()
  {
    if(!isset($this->target) || !isset($this->current_role)) return false;
    $index = array_search($this->target,$this->current_role);
    return isset($this->current_role[$index+1]);
  }

  public function connect()
  {
    if( !isset($this->target) ) return false;
    include_once('Net/SSH2.php');
    include_once('Crypt/RSA.php');
    $this->shell = new Net_SSH2($this->target);
    $key_path = home()."/.ssh/id_rsa";
    if( file_exists($key_path) )
    {
      $key = new Crypt_RSA();
      $key_status = $key->loadKey(file_get_contents($key_path));
      if(!$key_status) warn("ssh","Unable to load RSA key");
    }else
    {
      if( isset($this->password) )
        $key = $this->password;
    }

    if(!$this->shell->login($this->user,$key))
      warn("ssh","Login failed");
  }

  public function exec($cmd)
  {
    if($this->target && !$this->shell)
      $this->connect();
    if($this->shell)
      return $this->shell->exec($cmd);
    else
      return shell_exec($cmd);
  }

  public function put($what,$where)
  {
    if($this->target)
      $cmd = "rsync -avuz --quiet $what {$this->user}@{$this->target}:$where";
    else
      $cmd = "cp $what $where";
    return shell_exec($cmd);
  }

  public function get($what,$where)
  {
    if($this->target)
      $cmd = "rsync -avuz --quiet {$this->user}@{$this->target}:$what $where";
    else
      $cmd = "cp $what $where";
    return shell_exec($cmd);
  }

  public function query($query,$select_db)
  {
    if(!$this->mysql)
      if(!$this->db_connect()) return false;
    if( $select_db )
      mysql_select_db($this->wordpress["db"],$this->mysql);
    mysql_query($query,$this->mysql);
  }

  private function update_target($target)
  {
    if( $this->target == $target )
      return true;
    if( $this->shell )
      $this->shell = null;
    $this->target = $target;
    info("target",$this->target);
    return true;
  }

  private function init_scm()
  {
    require_once("Scm.php");
    foreach(glob("lib/Scm/*.php") as $file) require_once "Scm/".basename($file);
    $this->config["scm"] = (!isset($this->config["scm"]))? "Git" : ucwords(strtolower($this->config["scm"]));
    if( !$this->scm = new $this->config["scm"]($this->repository) )
      warn("scm","There is no recipe for {$this->config["scm"]}, perhaps create your own?");  
  }

  private function db_connect()
  {
    $this->mysql = @mysql_connect($this->wordpress["db_host"],$this->wordpress["db_user"],$this->wordpress["db_password"]);
    if( !$this->mysql )
      warn("mysql","there was a problem establishing a connection");
    return $this->mysql;
  }

}
?>
