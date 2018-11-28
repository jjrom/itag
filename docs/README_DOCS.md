#resto documentation

OpenAPI 3.O API document is generated from PHP annotation using swagger-php

## Packages installation

The following installation is for Mac OS X

### swagger-php

Requires homebrew to be installed

        # Install composer and swagger-php
        brew install composer
        composer global require zircote/swagger-php

        # Set path to openapi cli
        export PATH=~/.composer/vendor/bin/:$PATH

## widdershins
Require npm to be installed

        npm install -g widdershins

## Documentation generation

First generate OpenAPI file

        export ITAG_HOME=/path/to/itag/src/directory
        cd $ITAG_HOME/docs

        openapi --format json --output itag-api.json  --bootstrap constants.php $ITAG_HOME

Next generate markdown documentation

        widdershins --search false --code false --summary $ITAG_HOME/docs/itag-api.json -o $ITAG_HOME/docs/API.md

### HTML generation

        redoc-cli bundle itag-api.json
        mv redoc-static.html index.html


