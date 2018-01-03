#!/bin/sh

ASSETS_PATH='./assets'
ASSETS_LESS="$ASSETS_PATH/less"
ASSETS_TS="$ASSETS_PATH/typescript"

LESS_ENTRY_FILE='main.less'

PUBLIC_DIR='./public'
PUBLIC_CSS="$PUBLIC_DIR/css"
PUBLIC_JS="$PUBLIC_DIR/js"

NODE_PATH='./node_modules'

NODE_DEST_JS="$PUBLIC_JS/libs.js"
NODE_IMPORT_JS=(
    #'turbolinks/dist/turbolinks.js'
    #'highlightjs/highlight.pack.min.js'
)

NODE_DEST_CSS="$PUBLIC_CSS/libs.css"
NODE_IMPORT_CSS=(
    #'highlightjs/styles/default.css'
)

# delete old files, using find to avoid errors
echo "=> Cleanup"
find $ASSETS_TS -type f -name "*.d.ts" -delete -print
find $PUBLIC_CSS -type f -name "*.css" -delete -print
find $PUBLIC_JS -type f -name "*.js" -delete -print
echo

# styles
echo
echo "=> LESS"
for STYLE_DIR in $ASSETS_LESS/*/; do
STYLE_NAME=`basename $STYLE_DIR | tr '[A-Z]' '[a-z]'`
echo "==> $STYLE_NAME"
lessc --verbose $STYLE_DIR/$LESS_ENTRY_FILE $PUBLIC_CSS/$STYLE_NAME.css
echo
done

# scripts
echo
echo "=> TypeScript"
for SCRIPT_DIR in $ASSETS_TS/*/; do
SCRIPT_NAME=`basename $SCRIPT_DIR`
SCRIPT_NAME_LOWER=`echo $SCRIPT_NAME | tr '[A-Z]' '[a-z]'`
echo "==> $SCRIPT_NAME"
find $SCRIPT_DIR -name "*.ts" | xargs tsc \
    -d \
    -t es5 \
    --listFiles \
    --listEmittedFiles \
    --noImplicitAny \
    --removeComments \
    --outFile $PUBLIC_JS/$SCRIPT_NAME_LOWER.js
mv -v $PUBLIC_JS/$SCRIPT_NAME_LOWER.d.ts $ASSETS_TS/$SCRIPT_NAME.d.ts
echo
done

# node imports
echo
echo "=> NPM imports"

echo "==> JavaScript"
echo "Creating $NODE_DEST_JS"
touch $NODE_DEST_JS
for FILE in "${NODE_IMPORT_JS[@]}"; do
echo "===> $FILE"
cat "$NODE_PATH/$FILE" >> $NODE_DEST_JS
done
echo

echo "==> CSS"
echo "Creating $NODE_DEST_CSS"
touch $NODE_DEST_CSS
for FILE in "${NODE_IMPORT_CSS[@]}"; do
echo "===> $FILE"
cat "$NODE_PATH/$FILE" >> $NODE_DEST_CSS
done


