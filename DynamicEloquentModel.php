<?php

namespace App;

// TODO: Dependencies?
use Illuminate\Database\Eloquent\Model;
use \App\Models\Object as Object;

trait HasPreLoadedRelationships
{
    protected function newRelatedInstance($class)
    {
        // NOTE: allow for $class to be passed in as existing instance
        $class = is_string($class) ? new $class : $class;
        return tap($class, function ($instance) {
            if (! $instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }  
}
class OverloadedModel extends Model 
{
  use HasPreLoadedRelationships;
}

class DynamicEloquentModel extends OverloadedModel
{
    protected $connection = 'tenant_db';
    public $timestamps = false;
    protected $callableTenantMethods = []; 

    public function __construct($object) 
    {
        if (!($object instanceof Object)) { parent::__construct($object); return; }

        $this->table = $object->slug;

        foreach ($object->fields as $field) {
          if ($field->type == 'relation') {
            $this->callableTenantMethods[$field->name] = $field;
          } else {
            $this->fillable[] = $field->name;
          }
        }
    }

    
    public function __call ($method, $arguments = null)
    {
        try {
        	if (!in_array($method, array_keys($this->callableTenantMethods)))
          {
          	if ($arguments !== null) {
                return parent::__call($method, $arguments);
          	}
  	        return parent::__call($method);
          }

          $type = $this->callableTenantMethods[$method]->options->kind;
          $model = $this->callableTenantMethods[$method]->model_instance;
          
          $foreign_key = $method . '_id';
          $local_key = 'id';          
          
          // TODO: depending on the relation type the format and order of the local and foreign key differs
          return $this->$type($model, $foreign_key, $local_key);

        } catch (Exception $e) {
          dd($e);
        }
    }



}
