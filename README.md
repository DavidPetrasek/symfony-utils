# Installation

`composer require psys/symfony-utils`


## Add this to services.yaml:

### _defaults:
``` yaml
bind:
    $projectDir: '%kernel.project_dir%'
```

### at the end:
``` yaml
Psys\SymfonyUtils\FormErrors:
    autowire: true
Psys\SymfonyUtils\FileUploader:
    autowire: true
```

