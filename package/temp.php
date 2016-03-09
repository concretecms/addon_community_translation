<?php
$locale = Core::make('community_translation/locale')->find('it_IT');
$translatable = Core::make('community_translation/translatable')->find(17);
echo $translatable->getText(), "\n";
var_dump(
    Core::make('community_translation/editor')->getTranslatableData(
        $locale,
        $translatable,
        true
    )
);