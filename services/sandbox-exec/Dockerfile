FROM emscripten/emsdk:3.1.46 AS wasm-builder

RUN git clone --depth=1 --branch=php-8.2.10 https://github.com/php/php-src

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        autoconf \
        bison \
        pkg-config \
        re2c \
        && \
    :

RUN cd php-src && \
    ./buildconf --force && \
    emconfigure ./configure \
        --disable-all \
        --disable-mbregex \
        --disable-fiber-asm \
        --disable-cli \
        --disable-cgi \
        --disable-phpdbg \
        --enable-embed=static \
        --enable-short-tags \
        --enable-mbstring \
        --without-iconv \
        --without-libxml \
        --without-pcre-jit \
        --without-pdo-sqlite \
        --without-sqlite3 \
        && \
    EMCC_CFLAGS='-s ERROR_ON_UNDEFINED_SYMBOLS=0' emmake make -j$(nproc) && \
    mv libs/libphp.a .. && \
    make clean && \
    git clean -fd && \
    :

COPY wasm/php-wasm.c /src/

RUN cd php-src && \
    emcc \
        -c \
        -o php-wasm.o \
        -I . \
        -I TSRM \
        -I Zend \
        -I main \
        ../php-wasm.c \
        && \
    mv php-wasm.o .. && \
    make clean && \
    git clean -fd && \
    :

RUN emcc \
        -s ENVIRONMENT=node \
        -s ERROR_ON_UNDEFINED_SYMBOLS=0 \
        -s EXPORTED_RUNTIME_METHODS='["ccall"]' \
        -s EXPORT_ES6=1 \
        -s INITIAL_MEMORY=16777216 \
        -s INVOKE_RUN=0 \
        -s MODULARIZE=1 \
        -o php-wasm.js \
        php-wasm.o \
        libphp.a \
        ;

FROM node:20.7

WORKDIR /app

RUN mkdir /app/lib

COPY --from=wasm-builder /src/php-wasm.js /src/php-wasm.wasm /app/lib/

COPY index.js package.json package-lock.json /app/
COPY lib/exec.js /app/lib/

RUN npm install

ENTRYPOINT ["node", "index.js"]

EXPOSE 8888
