<?php

namespace BlackParadise\LaravelAdmin\Console;

use BlackParadise\LaravelAdmin\Core\TypeFromTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class Install extends Command
{
    protected $signature = 'bpadmin:install';

    protected $description = 'Generate BPModel files';

    public function handle()
    {
        $this->info('Start generate!');
        if (!File::exists(app_path('/BPAdmin'))) {
            File::makeDirectory(app_path('/BPAdmin'));
        }

        foreach(config('bpadmin.entities') as $key => $value) {
            $name = snakeToPascalCase($key);
            $this->info('Generation for: '.$name.' started!');

            if (!File::exists(app_path().'/BPAdmin/'.$name.'.php')) {
                $fillables = (new TypeFromTable())->getTypeList(new $value);
                $stub = File::get(__DIR__.'/../stubs/Model.stub');
                $stub = str_replace('{{className}}',$name,$stub);
                $stub = str_replace('{{name}}',$key,$stub);
                $stub = str_replace('{{modelPath}}',$value,$stub);
                $stub = str_replace('{{fieldTypes}}', $this->arrayToString($fillables), $stub);
                $stub = str_replace('{{searchFields}}', "'".array_keys($fillables)[0]."'", $stub);
                $stub = str_replace('{{tableHeaderFields}}', "id','".array_keys($fillables)[0],$stub);
                $stub = str_replace('{{showPageFields}}', "id','".array_keys($fillables)[0],$stub);

                File::put(app_path().'/BPAdmin/'.$name.'.php',$stub);
            }
            $this->info('Generation for: '.$name.' ended!');
        }
        $this->info('BPModels generated!');
    }

    private function arrayToString(array $array, $level = 2) {
        $tab = "    "; // Визначення табуляції як чотири пробіли
        $indent = str_repeat($tab, $level); // Створення відступу для поточного рівня вкладеності
        $result = "[\n";
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = $this->arrayToString($value, $level + 1); // Рекурсивний виклик для вкладених масивів зі збільшенням рівня вкладеності
            } else {
                $value = var_export($value, true); // Використання var_export для елементарних значень
            }
            $result .= $indent . var_export($key, true) . " => " . $value . ",\n"; // Форматування рядка з відступом
        }
        $result .= str_repeat($tab, $level - 1) . "]"; // Закриття масиву з правильним відступом
        return $result;
    }
}
