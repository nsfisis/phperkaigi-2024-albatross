import hljs from 'highlight.js/lib/core';
import php from 'highlight.js/lib/languages/php';
import plaintext from 'highlight.js/lib/languages/plaintext';
import 'highlight.js/styles/github.css';
import 'bootstrap/dist/css/bootstrap.css';

document.addEventListener('DOMContentLoaded', () => {
  hljs.registerLanguage('php', php);
  hljs.registerLanguage('plaintext', plaintext);
  hljs.highlightAll();
});

console.log(`#Hooray!Albatross!`);
