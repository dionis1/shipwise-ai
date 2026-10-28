<?php
return (new PhpCsFixer\Config())->setRules(['@Symfony' => true, 'yoda_style' => false])->setFinder(PhpCsFixer\Finder::create()->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/migrations']));
