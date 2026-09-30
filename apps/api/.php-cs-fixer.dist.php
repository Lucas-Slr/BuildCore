<?php
return (new PhpCsFixer\Config())->setRules(['@PSR12' => true])->setFinder(PhpCsFixer\Finder::create()->in(__DIR__.'/src')->in(__DIR__.'/tests'));
