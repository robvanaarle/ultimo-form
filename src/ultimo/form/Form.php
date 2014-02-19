<?php

namespace ultimo\form;

class Form implements \ArrayAccess {
  
  /**
   * The set fields and values of the forms. A hashtable with the fieldnames as
   * key and their values as value.
   * @var array
   */
  protected $fields = array();
  
  /**
   * The validation chains for each field. A hashtable with the fieldnames as
   * key and validation chains as value.
   * @var array
   */
  protected $validationChains = array();
  
  /**
   * The namespaces to look for a validator if it is appended without a fully
   * qualified name.
   * @var array
   */
  protected $validationNamespaces = array('', 'ultimo\validation\validators');
  
  /**
   * The wrapper definitions. A hashtable with the following keys:
   * wrapperFieldNames: The wrapper field names.
   * wrappedFieldNames: The wrapped field names.
   * toConverter: The callback function to convert wrapper field values to
   * wrapped field values.
   * fromConverter: The callback function to convert wrapped field values to
   * wrapper field values.
   * toArgs: Extra arguments for the to convertor callback.
   * fromArgs: Extra arguments for the from convertor callback.
   * @var array
   */
  protected $wrappers = array();
  
  /**
   * The configuration of the form. A hashtable with config keys as key and
   * their values as value.
   * @var array
   */
  protected $config = array();
  
  const ARRAY_DELIMITER = ':';
  
  /**
   * Constructor.
   * @param array $fields The initial fieldnames with values.
   * @param array $config The configuration.
   */
  public function __construct($fields = array(), $config = array()) {
    $this->setConfig($config);
    $this->init();
    $this->fromArray($fields);
  }
  
  /**
   * Called at the end of the constructor. A child form can initiazlize itself.
   * I.e. adding validators and wrappers.
   */
  protected function init() { }
  
  /**
   * Adds wrapper fields for one or more form fields. Wrapper fields enable
   * custom formatting of required fields. I.e. A controller expects a field
   * 'datetime' of the format 'Y-m-d H:i:s'. But the UI designed decided to
   * create two fields, a 'date' field of the format 'd-m-Y' and a 'time' field
   * of the format 'H:i:s'. In classic MVC form design the controller had to
   * be adapted for this. With wrappers this is not neeed anymore.
   * @param array $wrapperFieldNames The wrapper field names.
   * @param array $wrappedFieldNames The wrapped field names.
   * @param callback $toConverter The callback function to convert wrapper
   * field values to wrapped field values.
   * @param callback $fromConverter The callback function to convert wrapped
   * field values to wrapper field values.
   * @param array $toArgs Extra arguments for the to convertor callback.
   * @param array $fromArgs Extra arguments for the from convertor callback.
   * @return Form This instance for fluid design.
   */
  public function addWrapper(array $wrapperFieldNames, array $wrappedFieldNames, $toConverter, $fromConverter, array $toArgs=array(), array $fromArgs=array()) {
    $this->wrappers[] = array(
      'wrapperFieldNames' => $wrapperFieldNames,
      'wrappedFieldNames' => $wrappedFieldNames,
      'toConverter' => $toConverter,
      'fromConverter' => $fromConverter,
      'toArgs' => $toArgs,
      'fromArgs' => $fromArgs
    );
    return $this;
  }
  
  /**
   * Appends a validation namespace. Appended namespaces are looked in when a
   * validator if it is appended without a fully qualified name.
   * @param string $namespace The validation namespace.
   * @return Form This instance for fluid design.
   */
  public function appendValidationNamespace($namespace) {
    $this->validationNamespaces[] = trim($namespace, '\\');
    return $this;
  }
  
  /**
   * Returns the value of a field in array style.
   * @param string $offset The name of the field to retreive.
   * @return mixed The value of the field to retreive.
   */
  public function offsetGet($offset) {
    if (array_key_exists($offset, $this->fields)) {
      return $this->fields[$offset];
    } else {
      return '';
    }
  }
  
  /**
   * Sets the value of a field in array style.
   * @param string $offset The name of the field to set.
   * @param mixed $value The value to set the field to.
   */
  public function offsetSet($offset, $value) {
    $this->fields[$offset] = $value;
    $this->onDataChanged();
  }
  
  /**
   * Returns whether a field exits in this model in array style.
   * @param string $offset The name of the field to check the existence of.
   * @return boolean Whether the field exists in this model.
   */
  public function offsetExists($offset) { 
    return array_key_exists($offset, $this->fields);
  }
  
  /**
   * Unsets a field array style.
   * @param string $offset The name of the field to unset
   */
  public function offsetUnset($offset) {
    unset($this->fields[$offset]);
  }
  
  /**
   * Event called when one or more form values have been added or changed.
   * Enter description here ...
   */
  protected function onDataChanged() { }
  
  /**
   * Creates and returns a validator with the specified qualified name. The
   * qualified name is converted to a fully qualified name by prepending the
   * appened validation namespaces, until an existing class is found. If no
   * validator is found, an FormException is thrown.
   * @param string $qName The qualified name of the validator.
   * @param array $constructorArgs The constructor arguments for the validator.
   * @return \ultimo\validation\Validator The validator with the specified
   * qName.
   */
  protected function getValidator($qName, array $constructorArgs = array()) {
    foreach ($this->validationNamespaces as $namespace) {
      $fqName = $namespace . '\\' . $qName;
      if (class_exists($fqName)) {
        $ref = new \ReflectionClass($fqName);
    
        if (count($constructorArgs) == 0) {
          return $ref->newInstance();
        } else {
          return $ref->newInstanceArgs($constructorArgs);
        }
      }
    }
    
    throw new FormException("Could not find validator '{$qName}'.", FormException::VALIDATOR_NOT_FOUND);
  }
  
  /**
   * Appends a validator for a field.
   * @param string $fieldName The name of the field to append the validator to.
   * @param string $validatorQName The qualified name of the validator.
   * @param array $constructorArgs The constructor arguments for the validator.
   * @return Form This instance for fluid design.
   */
  public function appendValidator($fieldName, $validatorQName, array $constructorArgs = array()) {    
    if (!isset($this->validationChains[$fieldName])) {
      $this->validationChains[$fieldName] = new \ultimo\validation\Chain();
    }
    
    $this->validationChains[$fieldName]->appendValidator($this->getValidator($validatorQName, $constructorArgs));
    return $this;
  }
  
  /**
   * Validates the form values with the appended validators.
   * @return boolean Whether the form values are valid.
   */
  public function validate() {
    $valid = true;
    foreach ($this->validationChains as $fieldName => $chain) {
      if (!$chain->isValid($this[$fieldName])) {
        $valid = false;
      }
    }
    return $valid;
  }
  
  /**
   * Returns whether a field is valid. This only works if 'validate()' was
   * called first.
   * @param string $fieldName The name of the field.
   * @return boolean Whether the field is valid.
   */
  public function isValid($fieldName) {
    // If no validators are appended, then this field is valid by default.
    if (!isset($this->validationChains[$fieldName])) {
      return true;
    }
    
    return 0 == count($this->validationChains[$fieldName]->getErrors());
  }
  
  /**
   * Returns the error messages of all or one field. Optionally they can be
   * passed to a translator before returning.
   * @param string $fieldName The name of the field to get the error messages
   * for, or null if the error messages of all fields must be returned.
   * @param \ultimo\validation\Translator $translator The translator to
   * translate the error messages with, or null if the error messages don't need
   * to be translated.
   * @return array The error messages.
   */
  public function getErrorMessages($fieldName, \ultimo\validation\Translator $translator) {
    if ($fieldName === null) {
      $messages = array();
      foreach ($this->validationChains as $fieldName => $chain) {
        $messages[$fieldName] = $chain->getMessages($translator);
      }
    } else {
      if (!isset($this->validationChains[$fieldName])) {
        $messages = array();
      } else {
        $messages = $this->validationChains[$fieldName]->getMessages($translator);
      }
    }
    return $messages;
  }
  
  /**
   * Returns the errors of all or one field.
   * @param string $fieldName The name of the field to get the errors for, or
   * null if the error messages of all fields must be returned.
   * @param boolean $wrappedFallback Whether to append errors from wrapped
   * fields when the field itself contains to errors.
   * @return array The errors.
   */
  public function getErrors($fieldName=null, $wrappedFallback=true) {
    if ($fieldName === null) {
      $errors = array();
      foreach ($this->validationChains as $fieldName => $chain) {
        $errors[$fieldName] = $chain->getErrors();
        if ($wrappedFallback && empty($errors[$fieldName])) {
          foreach ($this->getWrappedFields($fieldName) as $wrappedFieldName) {
            $errors[$fieldName] = array_merge($errors[$fieldName], $this->getErrors($wrappedFieldName, false));
          }
        }
      }
    } else {
      if (!isset($this->validationChains[$fieldName])) {
        $errors = array();
      } else {
        $errors = $this->validationChains[$fieldName]->getErrors();
        if ($wrappedFallback && empty($errors)) {
          foreach ($this->getWrappedFields($fieldName) as $wrappedFieldName) {
            $errors = array_merge($errors, $this->getErrors($wrappedFieldName, false));
          }
        }
      }
    }
    return $errors;
  }
  
  /**
   * Adds a custom error to a field.
   * @param string $fieldName The name of the field to add a custom error to.
   * @param string $error The key of the error text.
   * @return Form This instance for fluid design.
   */
  public function addError($fieldName, $error) {
    if (!isset($this->validationChains[$fieldName])) {
      $this->validationChains[$fieldName] = new \ultimo\validation\Chain();
    }
    
    $this->validationChains[$fieldName]->addCustomError($error);
    return $this;
  }
  
  /**
   * Sets values of fields.
   * @param array $fields A hashtable with fieldnames as key and their values
   * as value.
   * @return Form This instance for fluid design.
   */
  public function fromArray($fields) {
    // make sure controllers don't have to check whether the request data is
    // an array
    if (!is_array($fields)) {
      return;
    }
    
    // convert the multi-dimenstional hashtable to a flat hashtable
    $flatten = array();
    reset($fields);
    $pathArrays = array(&$fields);
    $pathNames = array();
    
    while (!empty($pathArrays)) {
      $lastPathArray = &$pathArrays[count($pathArrays)-1];
      $nextField = each($lastPathArray);
      
      if ($nextField === false) {
        array_pop($pathArrays);
        array_pop($pathNames);
        continue;
      }

      list($name, $value) = $nextField;
      
      if (!is_array($value)) {
        $flattenName = implode(self::ARRAY_DELIMITER, array_merge($pathNames, array($name)));
        $flatten[$flattenName] = $value;
      } else {
        reset($value);
        $pathArrays[] = $value;
        $pathNames[] = $name;
      }
    }
    
    $this->fields = array_merge($this->fields, $flatten);
    $this->processWrappers();
    $this->onDataChanged();
    return $this;
  }
  
  /**
   * Sets a configuration.
   * @param $config The configuration.
   */
  protected function setConfig(array $config) {
    $this->config = array_merge($this->config, $config);
  }
  
  /**
   * Returns the configuration value with the specified key.
   * @param array $key The configuration key to get the value for.
   * @return mixed The value of the configuration key, or null if the key does
   * not exist in the configuration.
   */
  public function getConfig($key) {
    if (!isset($this->config[$key])) {
      return null;
    }
    return $this->config[$key];
  }
  
  /**
   * Returns all wrapped field names a wrapper field name wraps.
   * @param string $wrapperFieldName The wrapper field name.
   * @return array The wrapped field names.
   */
  protected function getWrappedFields($wrapperFieldName) {
    $wrappedFieldNames = array();
    foreach ($this->wrappers as $wrapper) {
      if (in_array($wrapperFieldName, $wrapper['wrapperFieldNames'])) {
        $wrappedFieldNames = array_merge($wrappedFieldNames, $wrapper['wrappedFieldNames']);
      }
    }
    
    return $wrappedFieldNames;
  }
  
  /**
   * Executes the callbacks of the wrappers. For each wrapper the to- or from-
   * converter wrapper is executed, based on the values set in the form.
   */
  protected function processWrappers() {
    
    foreach ($this->wrappers as $wrapper) {
      
      $missingWrapperData = false;
      foreach ($wrapper['wrapperFieldNames'] as $wrapperFieldName) {
        if (!array_key_exists($wrapperFieldName, $this->fields)) {
          $missingWrapperData = true;
          break;
        }
      }
      
      $missingWrappedData = false;
      foreach ($wrapper['wrappedFieldNames'] as $wrappedFieldName) {
        if (!array_key_exists($wrappedFieldName, $this->fields)) {
          $missingWrappedData = true;
          break;
        }
      }
      
      if ($missingWrapperData === $missingWrappedData) {
        continue;
      } elseif ($missingWrappedData) {
        $args = array_merge(array($wrapper['wrapperFieldNames'], $wrapper['wrappedFieldNames']), $wrapper['toArgs']);
        call_user_func_array($wrapper['toConverter'], $args);
      } else {
        $args = array_merge(array($wrapper['wrapperFieldNames'], $wrapper['wrappedFieldNames']), $wrapper['fromArgs']);
        call_user_func_array($wrapper['fromConverter'], $args);
      }
    }
  }
  
  /**
   * Returns the value of a field, taking multi-dimensionality into account.
   * @param string $name Name of the field to get the value of.
   * @return mixed The value of the field with the specified name, or an empty
   * string if the field does not exist. 
   */
  public function getValue($name) {
    $fields = $this->toArray(true);
    if (!array_key_exists($name, $fields)) {
      return '';
    }
    
    return $fields[$name];
  }
  
  /**
   * Returns the form fields and values as hashtable.
   * @param boolean $multiDimensional Whether to return the fields as a multi-
   * dimensional hashtable. If true, delimiters in field names will be
   * interpreted.
   * @return array A hashtable with form fields as key and their values as
   * value.
   */
  public function toArray($multiDimensional = false) {
    if (!$multiDimensional) {
      return $this->fields;
    }
    
    // convert the flatten field hashtable to a multi-dimensional hashtable
    $multiDimensionalFields = array();
    foreach ($this->fields as $name => $value) {
      $nameElems = explode(self::ARRAY_DELIMITER, $name);
      $currentDimension = &$multiDimensionalFields;
      
      $fieldName = array_pop($nameElems);
      foreach ($nameElems as $nameElem) {
        if (!array_key_exists($nameElem, $currentDimension)) {
          $currentDimension[$nameElem] = array();
        }
        $currentDimension = &$currentDimension[$nameElem];
      }
      $currentDimension[$fieldName] = $value;
    }
    
    return $multiDimensionalFields;
  }
}