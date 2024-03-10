# Albatross.PHP


## これは何？

2024-03-07 から 2024-03-09 にかけて開催された [PHPerKaigi 2024](https://phperkaigi.jp/2024/) の中のコードゴルフ企画にて使用されたシステムのソースコードです。

現在は回答を締め切っていますが、ゴルフの問題や開催中の参加者の方々の回答は、以下のページから閲覧できます。

https://t.nil.ninja/phperkaigi/2024/golf/


## おおまかな動作の仕組み

本システムは、以下二つのコンポーネントからなります。

* [services/app/](./services/app/): [Slim Framework](https://www.slimframework.com/) で書かれたアプリケーションサーバ
* [services/sandbox-exec/](./services/sandbox-exec/): WebAssembly を使ったサンドボックス実行環境

コードゴルフの回答が期待する動作をしているかどうか検証するには、入力されたコードを実際に実行してみる必要があります。任意のコードを自由に実行させてしまうと、深刻な脆弱性に繋がってしまいます。

このシステムでは、環境の隔離に WebAssembly を用いています。具体的には、PHP の処理系を [Emscripten](https://emscripten.org/) を用いて WebAssembly に変換し、その上で入力された PHP コードを動かしています。PHP 処理系を WebAssembly へとコンパイルする部分については、[以前にブログ記事にまとめていますので、そちらをご参照ください](https://blog.nsfisis.dev/posts/2023-10-02/compile-php-runtime-to-wasm/)。


## ライセンス

このリポジトリ中のファイルには、[services/app/assets/favicon.svg](./services/app/assets/favicon.svg) を除いて、The MIT License が適用されます。ライセンス表記については [LICENSE](./LICENSE) をご覧ください。
