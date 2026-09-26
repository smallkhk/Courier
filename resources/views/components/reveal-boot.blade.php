{{-- Enables scroll-reveal before first paint; if app.js never loads, content is shown after 2.5s. --}}
<script>document.documentElement.classList.add('js');setTimeout(function(){if(!window.__revealReady)document.documentElement.classList.add('no-reveal')},2500);</script>
<style>.no-reveal [data-reveal]{opacity:1!important;transform:none!important}</style>
