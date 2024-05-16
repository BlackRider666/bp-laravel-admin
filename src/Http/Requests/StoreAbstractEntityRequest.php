<?php

namespace BlackParadise\LaravelAdmin\Http\Requests;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Core\StorageManager;
use Illuminate\Foundation\Http\FormRequest;

class StoreAbstractEntityRequest extends FormRequest
{
    private BpModel $BPModel;

    public function __construct(BPModel $BPModel)
    {
        $this->BPModel = $BPModel;
    }

    public function rules():array
    {
        $rules = $this->BPModel->getStoreRules();

        return $rules;
    }

    protected function failedValidation($validator)
    {
        \Log::info($validator->errors()->messages());
        parent::failedValidation($validator);
    }

    protected function passedValidation()
    {
        $fileFields = $this->BPModel->getFileFields();
        $path = $this->BPModel->filePath;
        $validatedData = $this->validator->validated();
        foreach ($this->allFiles() as $key => $value) {
            if (in_array($key,$fileFields)) {
                $validatedData[$key] = (new StorageManager())
                    ->saveFile($value,$path.'/'.$key);
            }
        }
        $this->validator->setData($validatedData);
    }
}
