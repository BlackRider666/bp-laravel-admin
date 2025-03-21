<?php

use Illuminate\Http\RedirectResponse;

if (!function_exists('bpadmin_navbar_format_items')) {

    /**
     * @param array $items
     * @return array|array[]
     */
    function bpadmin_navbar_format_items(array $items): array
    {
        $patterns = [];
        foreach($items as $key => $value) {
            $patterns[$key][] = 'bpadmin.'.$key.'.index';
            $patterns[$key][] = 'bpadmin.'.$key.'.create';
            $patterns[$key][] = 'bpadmin.'.$key.'.show';
            $patterns[$key][] = 'bpadmin.'.$key.'.edit';
            if (array_key_exists('items',$value)) {
                foreach ($value['items'] as $keySub => $valueSub){
                    $patterns[$keySub][] = 'bpadmin.'.$keySub.'.index';
                    $patterns[$keySub][] = 'bpadmin.'.$keySub.'.create';
                    $patterns[$keySub][] = 'bpadmin.'.$keySub.'.show';
                    $patterns[$keySub][] = 'bpadmin.'.$keySub.'.edit';
                }
            }
        }
        array_walk($items,  static function(&$item, $key) use ($patterns) {
            $entityClass = config("bpadmin.entities.$key");

            // Якщо є policy, але юзер не має доступу – прибираємо пункт
            if ($entityClass && \Illuminate\Support\Facades\Gate::getPolicyFor($entityClass) && !\Illuminate\Support\Facades\Gate::allows('viewAny', $entityClass)) {
                $item = null;
                return;
            }

            $item['title'] = ucwords(str_replace('_',' ',$key));
            $item['href'] = route('bpadmin.'.$key.'.index');
            $item['active'] = request()->routeIs($patterns[$key]);
            if (array_key_exists('items',$item)) {
                array_walk($item['items'],  static function(&$subItem, $subKey) use ($patterns) {
                    $entityClassSub = config("bpadmin.entities.$subKey");
                    if ($entityClassSub && \Illuminate\Support\Facades\Gate::getPolicyFor($entityClassSub) && !\Illuminate\Support\Facades\Gate::allows('viewAny', $entityClassSub)) {
                        $subItem = null;
                        return;
                    }
                    $subItem['title'] = ucwords(str_replace('_',' ',$subKey));
                    $subItem['href'] = route('bpadmin.'.$subKey.'.index');
                    $subItem['active'] = request()->routeIs($patterns[$subKey]);
                });
                $item['items'] = array_filter($item['items']);
            }
        });

        $items = array_filter($items);
        return [ 'pages' => [
                'title' => 'Home',
                'href'  =>  route('bpadmin.pages.index'),
                'active'    => request()->routeIs('bpadmin.pages.index'),
                'icon'      =>  'mdi-chart-areaspline'
            ]] + $items;
    }
}
if (!function_exists('bpadmin_select_format_items')) {

    /**
     * @param array $items
     * @return array|array[]
     */
    function bpadmin_select_format_items(array $items): array
    {
        array_walk($items, static function(&$value,$key) {
            $value['text'] = $value;
            $value['key'] = $key;
        });
        return $items;
    }
}

if (!function_exists('bpadmin_object_translatable_field')) {

    /**
     * @param array $items
     * @return array|array[]
     */
    function bpadmin_object_translatable_field(array $items): array
    {
        $res = [];
        foreach ($items as $item) {
            $res[$item] = '';
        }
        return $res;
    }
}

if (!function_exists('snakeToPascalCase')) {

    /**
     * @param string $string
     * @return string
     */
    function snakeToPascalCase($string) {
        return str_replace('_', '', ucwords($string, '_'));
    }
}

if (!function_exists('redirectToIndexModelPage')) {

    /**
     * @param string $name
     * @return RedirectResponse
     */
    function redirectToIndexModelPage(string $name): RedirectResponse
    {
        return redirect()->route('bpadmin.'.$name.'.index');
    }
}
