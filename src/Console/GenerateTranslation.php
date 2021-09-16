<?php

namespace BlackParadise\LaravelAdmin\Console;

use Illuminate\Console\Command;

class GenerateTranslation extends Command
{
    protected $signature = 'bpadmin:translation-generate';

    protected $description = 'Generate translation for your models';

    public function handle()
    {
        $this->info('Start generate translations!');
        $directoryPath = resource_path('lang/vendor/bpadmin/en');
        foreach(config('bpadmin.dashboard.entities') as $key => $options) {
            $this->info('Generation for: '.ucfirst($key).' started!');
            $fillables = (new $options['entity'])->getFillable();
            $translation = '<?php'.PHP_EOL.' return ['.PHP_EOL;
            foreach ($fillables as $field) {
                $translation.= "\t"."'".$field."' => '".ucfirst($field)."',".PHP_EOL;
            }
            $translation.='];';
            $path = $directoryPath.'/'.$key.'.php';
            file_put_contents($path, $translation);
            $this->info('Generation for: '.ucfirst($key).' ended!');
        }
        $this->info('Translations generated!');
    }
}
