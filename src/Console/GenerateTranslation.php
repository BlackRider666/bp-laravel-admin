<?php

namespace BlackParadise\LaravelAdmin\Console;

use BlackParadise\LaravelAdmin\Core\Services\TypeFromTable;
use Illuminate\Console\Command;

class GenerateTranslation extends Command
{
    protected $signature = 'bpadmin:translation-generate';

    protected $description = 'Generate translation for your models';

    public function handle()
    {
        $this->info('Start generate translations!');
        $directoryPath = resource_path('lang/vendor/bpadmin/en');
        foreach(config('bpadmin.entities') as $key => $options) {
            $this->info('Generation for: '.ucfirst($key).' started!');
            $fillables = (new TypeFromTable())->getTypeList(new $options['entity']);
            $translation = '<?php'.PHP_EOL.' return ['.PHP_EOL;
            $translation.= "\t"."'name' => '".ucfirst($key)."',".PHP_EOL;
            foreach ($fillables as $k => $value) {
                $field = substr($k, -6) === 'method' ? substr($k, 0, -7) : $k;
                $translation.= "\t"."'".$k."' => '".ucfirst($field)."',".PHP_EOL;
            }
            $translation.= "\t"."'actions' => 'Actions',".PHP_EOL;
            $translation.='];';
            $path = $directoryPath.'/'.$key.'.php';
            file_put_contents($path, $translation);
            $this->info('Generation for: '.ucfirst($key).' ended!');
        }
        $this->info('Translations generated!');
    }
}
