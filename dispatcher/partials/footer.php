</div><!-- /.d-main -->
</div><!-- /.d-shell -->
<script>
// Mobile sidebar toggle
var toggle = document.getElementById('d-menu-toggle');
var sidebar = document.getElementById('d-sidebar');
if(toggle && sidebar){
  toggle.addEventListener('click',function(){sidebar.classList.toggle('open');});
  document.addEventListener('click',function(e){if(sidebar.classList.contains('open')&&!sidebar.contains(e.target)&&e.target!==toggle)sidebar.classList.remove('open');});
}
</script>
</body></html>
