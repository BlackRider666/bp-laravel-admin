<?php

namespace BlackParadise\LaravelAdmin\Http\Requests;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Core\StorageManager;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAbstractEntityRequest extends FormRequest
{
    private BpModel $BPModel;

    public function __construct(BPModel $BPModel)
    {
        $this->BPModel = $BPModel;
    }

    public function rules():array
    {
        $rules = $this->BPModel->getUpdateRules((int)$this->id);
        return $rules;
    }

    protected function failedValidation($validator)
    {
        \Log::info($validator->errors()->messages());
        parent::failedValidation($validator);
    }

    protected function passedValidation()
    {
        $fields = $this->BPModel->getFileFields();
        $fields[] = 'id';
        $model = $this->BPModel->findQuery($this->id, $fields);
        $path = $this->BPModel->filePath;
        $validatedData = $this->validator->validated();
        foreach ($this->allFiles() as $key => $value) {
            if (in_array($key,$fields)) {
                if ($model->$key !== null) {
                    (new StorageManager())->deleteFile($model->$key,$path.'/'.$key);
                }
                $validatedData[$key] = (new StorageManager())
                    ->saveFile($value,$path.'/'.$key);
            }
        }
        $this->validator->setData($validatedData);
    }
}
