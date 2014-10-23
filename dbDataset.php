<?php

include_once 'dbCredentials.inc.php';

class dbRow {

    private $_changes=array();
    private $_Fields;
    
    public function __construct($FieldsA) {
        $this->_changes=array();
        if (!is_array($FieldsA)) {
            throw new Exception ('Error: Fields variable isn\'t an array');
        }
        $this->_Fields=$FieldsA;         
    }
    public function __get($name) {       
        
        if (array_key_exists($name, $this->_Fields)) {                        
            if (!empty($this->_changes)) {               
               return array_key_exists($name,$this->_changes)?$this->_changes[$name]:$this->_Fields[$name]; 
            }
            else {
                return $this->_Fields[$name]; 
            }        
        }
        else if (strpos($name,'OLD_')!==false && array_key_exists(str_replace('OLD_','',$name),$this->_Fields)) {
            return $this->_Fields[str_replace('OLD_','',$name)];
        }
        else if ($name=="Names") {
            return array_keys($this->_Fields);
        }
    }
    public function __set($name,$value) {
        if (array_key_exists($name, $this->_Fields)) {
            $this->_changes[$name]=$value;  
        }
        
    }
    public function Restore() {
        EmptyArray($this->_changes);        
    }    
}
class dbArrayParam {
    private $_needles;
    private $_stack;        
    public function __construct($ParamsArray=NULL) {        
        if (!is_null($ParamsArray) && !empty($ParamsArray)) {
            $this->CreateArray($ParamsArray);
        }
        else {
            $this->_needles=array();
            $this->_stack=array();
        }
    }
            
    public function Count() {
        return count($this->_needles);
    }
    public function __get($name) {
        if (array_key_exists($name, $this->_stack)) {
            return $this->_stack[$name];
        }
    }
    public function __set($name, $value) {
        if (array_key_exists($name, $this->_stack)) {
            if(is_string($value)) $this->_stack[$name]=trim($value);                        
            else $this->_stack[$name]=$value;                        
        }
    }
   

    public function GetSQLParamsA($sort=true) {        
        $output=array();        
        foreach($this->_needles as $name => $item) {
            foreach($item as $valuePos) {
                if (is_string($this->_stack[$name])) $output[$valuePos]=trim($this->_stack[$name]);                
                else $output[$valuePos]=$this->_stack[$name];                
            }            
        }        
        if ($sort) ksort($output);
        return $output; 
    }

    private function CreateArray($StartArray) {
        if (!is_array($StartArray)) {
            throw new Exception ("Can't create object because the StartArray parameter isn't an array");
        }
        if (array_keys($StartArray) !== range(0, count($StartArray) - 1)) {            
            ob_start();
            var_dump($StartArray);
            $errorA=ob_get_clean();
            throw new Exception ("The parameter can't be an associteve array or array is empty $errorA");
        }
        if (isset($this->_needles)) {
            unset ($this->_needles);
        }
        if (isset($this->_stack)) {
            unset ($this->_stack);
        }        
        $this->_needles=array();
        $this->_stack=array();
        foreach ($StartArray as $k=>$v) {
            if (!array_key_exists($v, $this->_stack)) {
                $this->_needles[str_replace(":","",$v)]=array_keys($StartArray,$v);
                $this->_stack[str_replace(":","",$v)]="";                
            }            
        }        
        
    
    }

}
 class dbDataset {
    
    private $_select;
    private $_TrueSelect;
    private $_active;
    private $_rows;
    private $_Deleted;
    private $_params;
    private $_FieldTypes;
    private $_queryRes;

    private $_fieldsNames;
    private $_dbConnection;
    private $_SelectParamsA;
    
    private $_eof;
    private $_bof;
    private $_KeyEof;
    private $_KeyBof;    
    
    private $_update;
    private $_delete;
    
    private $_UpdateParamsA;    
    private $_DeleteParamsA;
    
    private $_TrueUpdate;
    private $_TrueDelete;
    
    public $updateRel;
    public $deleteRel;    
    
    public function __get($name) {
        if ($name=="selectSQL") { return $this->_select; }        
        if ($name=="updateSQL") { return $this->_update; }        
        if ($name=="deleteSQL") { return $this->_delete; }
        if ($name=="Params")    { return $this->_SelectParamsA;}       
        if ($name=="Fields")    { return current($this->_rows);}
        if ($name=="Empty")     { return !$this->_active||empty($this->_rows); }
        if ($name=="EOF")       { return $this->_eof;}
        if ($name=="BOF")       { return $this->_bof;}        
        
        if ($name=="Count")     { return count($this->_rows); }
        if ($name == "ArrayFields") {
            $AFields=array();
            if ($this->_active && !empty($this->_rows)) {
                foreach(current($this->_rows)->Names as $FName) {
                    $AFields[$FName]=current($this->_rows)->{$FName};
                }
            }
            return $AFields;
        }
    }
    
    public function __set($name, $value) {        
        if ($name=='selectSQL') { 
            if ($this->_active==TRUE) { throw new Exception("Opened dataset"); }                    
            else {                
               $this->_TrueSelect=preg_replace('^\x3a[0-9A-z]*[0-9A-z]^', '?', $value);               
               preg_match_all("^\x3a[0-9A-z]*[0-9A-z]^", $value, $this->_params);               
               $this->_SelectParamsA=new dbArrayParam($this->_params[0]);                                           
               
           }
        }
        if ($name=='updateSQL') {            
            if ($this->_active==TRUE) { throw new Exception("Opened dataset"); }                    
            else {                
               $this->_update=$value;           
               $this->_TrueUpdate=preg_replace('^\x3a[0-9A-z]*[0-9A-z]^', '?', $value);               
               preg_match_all("^\x3a[0-9A-z]*[0-9A-z]^", $value, $this->_params);                              
               $this->_UpdateParamsA=new dbArrayParam($this->_params[0]);                                      
           }
        }
        if ($name=='deleteSQL') {            
            if ($this->_active==TRUE) { throw new Exception("Opened dataset"); }                    
            else {                
               $this->_delete=$value;           
               $this->_TrueDelete=preg_replace('^\x3a[0-9A-z]*[0-9A-z]^', '?', $value);               
               preg_match_all("^\x3a[0-9A-z]*[0-9A-z]^", $value, $this->_params);                              
               $this->_DeleteParamsA=new dbArrayParam($this->_params[0]);                                      
           }
        }
    } 
    
    public function __construct() {        
        $this->_active=FALSE;
        $this->_rows=array();
        $this->_Deleted=array();
        $this->_params=array();
        $this->_fieldsNames=array();        
        $this->_FieldTypes=array();                
        $this->_dbConnection=ibase_pconnect(_DBSERVER.":"._DBFILE,_DBUSER,_DBPASSWD);               
    }
    
    private function translateSQL($SQLstring) {        
        $TrueSQL=preg_replace('^\x3a[0-9A-z]*[0-9A-z]^', '?', $SQLstring);
        $params=array();
        preg_match_all("^\x3a[0-9A-z]*[0-9A-z]^", $SQLstring, $params);
        return array('TrueSQL'=>$TrueSQL,'Params'=>$params[0]);
        
    }
    
    
    private function ExecuteStatement($QueryString,$ParamArray) {
       $dbCallArray=array();              
       $dbCallArray[]=$this->_dbConnection;
       $dbCallArray[]=$QueryString;       
       foreach($ParamArray->GetSQLParamsA() as $valueParam) {           
           $dbCallArray[]=$valueParam;                                   
       }       
       return call_user_func_array("ibase_query", $dbCallArray);         
    }
    public function ToArray() {
        $r=array();
        if (!empty($this->_rows) && current($this->_rows)!==NULL) {            
            foreach(current($this->_rows)->Names as $n) 
                $r[$n]=current($this->_rows)->{$n};
                
        }
        return empty($r)?NULL:$r;
    }

        public function Open() {        
       if ($this->_TrueSelect=="") {
            throw new Exception('Error: Can\'t open dataset, empty select statement on object class "'.get_class($this).'"');
       }       
       $this->_queryRes=$this->ExecuteStatement($this->_TrueSelect,$this->_SelectParamsA);
       
       if (!is_array($this->_fieldsNames)) {
          unset($this->_fieldsNames);       
          $this->_fieldsNames=array();
       }
       
       if (!is_array($this->_rows)) {
          unset($this->_rows);       
          $this->_rows=array();
       }      
       $this->_bof=false;
       $this->_eof=false;       
       while ($f=ibase_fetch_assoc($this->_queryRes)) {                      
           foreach ($f as $k=>$v) {
               if (!in_array($k,$this->_fieldsNames)) {
                   $this->_fieldsNames[]=$k;                                      
                }
           }
           $nRow=new dbRow($f);           
           if ($this->_bof===false) $this->_KeyBof=key($this->_rows);
           $this->_rows[]=$nRow;
           
       }       
       $this->_active=true;       
       if (count($this->_rows)==0) {
         $this->_eof=true;
         $this->_bof=true;
       }
       else {
           end($this->_rows);           
           $this->_KeyEof=key($this->_rows);
           reset($this->_rows);
       }      
    }
    public function ApplyChanges() {
       if ($this->_TrueUpdate!="" && isset($this->updateRel) && is_array($this->updateRel) && !empty($this->updateRel)) {
           foreach($this->_rows as $Row) {
               foreach($this->updateRel as $ori => $dest) { 
                   $this->_UpdateParamsA->{$ori}=$Row->{$dest};          
               }
                          
               $this->ExecuteStatement($this->_TrueUpdate,$this->_UpdateParamsA);           
           }                
       }       
       if ($this->_TrueDelete!="" && isset($this->deleteRel) && is_array($this->deleteRel) && !empty($this->deleteRel)) {                     
           foreach($this->_Deleted as $Row) {
               foreach($this->deleteRel as $ori => $dest) {
                   $this->_DeleteParamsA->{$ori}=$Row->{$dest};                                            
               }               
               $this->ExecuteStatement($this->_TrueDelete,$this->_DeleteParamsA);          
           }                               
       }      
    }
    public function Next() {
        if (!empty($this->_rows)) {             
            $p=next($this->_rows);
            if ($p===FALSE) $this->_eof=true;            
        }
        else $this->_eof=true;
            
        
    }
    public function Prior() {
        if (!empty($this->_rows)) {             
            $p=prior($this->_rows);            
            if ($p===FALSE) $this->_bof=true;
            
        }
        else $this->_bof=true;
            
        
    }    
    public function First() {
        if (!empty($this->_rows)) { 
            reset($this->_rows);             
        }
    }
    public function Last() {
        if (!empty($this->_rows)) { 
            end($this->_rows);             
        }
    }

    public function Close() {
        $this->_active=FALSE;                
        unset($this->_params);                       
        $this->_params=array();        
    }    
    public function Delete() {
        if (current($this->_rows)!=false) {                        
            $this->_Deleted[] = current($this->_rows);            
            $k=key($this->_rows);
            unset($this->_rows[$k]);                            
        }
    }   
    public function GetBlob($BlobField) {
        $b=current($this->_rows);
        if ($b!==FALSE) {
            $bInfo=ibase_blob_info($this->_dbConnection,$b->{$BlobField});
            if ($bInfo[0]>0) {
              $bHld=ibase_blob_open($this->_dbConnection,$b->{$BlobField});              
              return ibase_blob_get($bHld, $bInfo[0]);              
            }
        }
        return null;
        
    }
 }
//------------------------------------------------------------------------------------------------------
 class dbProcedure {
     private $_params;
     private $_procName;
     private $_dbConnection;     
     private $_results;
     private $_SQLBlock;
     private $_TrueSQL;
     private $_resource;
     

     public function __construct($AProcName=null) {       
        
        $this->_dbConnection=ibase_pconnect(_DBSERVER.":"._DBFILE,_DBUSER,_DBPASSWD);      
        if (!is_null($AProcName)) $this->SetProcedure($AProcName);
     }
     
     public function __get($name) {
         if ($name=="Params") return $this->_params;
         if ($name=="Procedure") return $this->_procName;         
         if ($name=="SQL") return $this->_SQLBlock;
     }
     
     
     public function __set($name, $value) {
         if ($name=="Procedure") $this->SetProcedure($value); 
         if ($name=="SQL") $this->SetBlock ($value);           
         
     }
     public function GetResults() {         
         return $this->_results;         
     }
     
     public function Execute() {
         if ($this->_TrueSQL!="") {                        
            $this->_resource=false;
            $dbCallArray=array();              
            $dbCallArray[]=ibase_prepare($this->_dbConnection,$this->_TrueSQL);
            foreach($this->_params->GetSQLParamsA() as $valueParam) {
                $dbCallArray[]=$valueParam;             
            }
           $this->_resource=call_user_func_array("ibase_execute", $dbCallArray);               
            try {
                if (is_resource($this->_resource)) {
                    $this->_results=ibase_fetch_assoc($this->_resource);                   
                }                
            } catch (Exception $ex) {                
                throw $ex;
            }
         }         
     }

     private function SetProcedure($AProcName) {
         $qResult=ibase_query($this->_dbConnection,
                 'select rdb$procedure_name
                    from rdb$procedures
                   where rdb$procedure_name=?',$AProcName);
         if ($f=ibase_fetch_assoc($qResult)) {         
           $qResult=ibase_query($this->_dbConnection,
               'SELECT trim(rdb$procedure_parameters.rdb$parameter_name) ParamName '
                   . 'FROM rdb$procedure_parameters, rdb$fields '
                   . 'WHERE rdb$fields.rdb$field_name = rdb$procedure_parameters.rdb$field_source '
                   . 'AND rdb$procedure_parameters.rdb$parameter_type=0 '
                   . 'and rdb$procedure_name=? ORDER BY rdb$fields.rdb$field_name',$AProcName);    
           
           $SQL="EXECUTE PROCEDURE $AProcName (";
           $strParam="";
           while ($Params=ibase_fetch_assoc($qResult)) 
               $strParam.=($strParam!=""?",":"").":".trim($Params['PARAMNAME']);                
           $SQL=$SQL.$strParam.")";           
           
           $this->_TrueSQL=preg_replace('^\x3a[0-9A-z]*[0-9A-z]^', '?',$SQL);           
           $Params=array();
           preg_match_all("^\x3a[0-9A-z]*[0-9A-z]^", $SQL, $Params);           
           $this->_params=new dbArrayParam(!empty($Params[0])?$Params[0]:NULL);
           $this->_procName=$AProcName;
           $this->_SQLBlock="";
        }
        else throw new Exception("Error: stored procedure not exists");                 
     }
     private function SetBlock($SQLStatementBlock) {
         $Params=array();
         $this->_SQLBlock=$SQLStatementBlock;
         $p=strpos(strtoupper($SQLStatementBlock),"BEGIN");
         if ($p!==FALSE) {
           $this->_TrueSQL=preg_replace('^\x3a[0-9A-z]*[0-9A-z]^', '?', substr($SQLStatementBlock,0,$p)).
                    " ".substr($SQLStatementBlock,$p);               
           preg_match_all("^\x3a[0-9A-z]*[0-9A-z]^", substr($SQLStatementBlock,0,$p), $Params);
         }
         else {
             $this->_TrueSQL=preg_replace('^\x3a[0-9A-z]*[0-9A-z]^', '?',$SQLStatementBlock);
             preg_match_all("^\x3a[0-9A-z]*[0-9A-z]^", $SQLStatementBlock, $Params);                              
         }                 
         
         $this->_params=new dbArrayParam(!empty($Params[0])?$Params[0]:NULL);           
         $this->_procName="";
         unset($Params);
     }
    
 }