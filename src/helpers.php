<?php

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
        array_walk($items,  static function(&$item, $key) use ($patterns){
            $item['title'] = ucwords(str_replace('_',' ',$key));
            $item['href'] = route('bpadmin.'.$key.'.index');
            $item['active'] = request()->routeIs($patterns[$key]);
            if (array_key_exists('items',$item)) {
                array_walk($item['items'],  static function(&$subItem, $key) use ($patterns){
                    $subItem['title'] = ucwords(str_replace('_',' ',$key));
                    $subItem['href'] = route('bpadmin.'.$key.'.index');
                    $subItem['active'] = request()->routeIs($patterns[$key]);
                });
            }
        });
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
