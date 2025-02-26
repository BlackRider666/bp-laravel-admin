<?php

namespace BlackParadise\LaravelAdmin\Http\Requests;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;

class BaseAbstractEntityRequest extends FormRequest
{
    protected BPModel $BPModel;

    /**
     * @return void
     */
    public function prepareForValidation(): void
    {
        $this->BPModel = app(BPModel::class);
    }

    /**
     * @throws ValidationException
     * @throws JsonException
     */
    protected function failedValidation($validator): void
    {
        Log::alert(json_encode($validator->errors()->messages(), JSON_THROW_ON_ERROR));
        parent::failedValidation($validator);
    }

//    protected function passedValidation()
//    {
//        $fields = $this->BPModel->getFileFields();
//        $fields[] = 'id';
//        $model = $this->BPModel->findQuery($this->id, $fields);
//        $path = $this->BPModel->filePath;
//        $validatedData = $this->validator->validated();
//        foreach ($this->allFiles() as $key => $value) {
//            if (in_array($key,$fields)) {
//                if ($model->$key !== null) {
//                    (new StorageManager())->deleteFile($model->$key,$path.'/'.$key);
//                }
//                $validatedData[$key] = (new StorageManager())
//                    ->saveFile($value,$path.'/'.$key);
//            }
//        }
//        $this->validator->setData($validatedData);
//        protected function passedValidation()
//    {
//        $fileFields = $this->BPModel->getFileFields();
//        $path = $this->BPModel->filePath;
//        $validatedData = $this->validator->validated();
//        foreach ($this->allFiles() as $key => $value) {
//            if (in_array($key,$fileFields)) {
//                $validatedData[$key] = (new StorageManager())
//                    ->saveFile($value,$path.'/'.$key);
//            }
//        }
//        $this->validator->setData($validatedData);
//    }
//    }
}
