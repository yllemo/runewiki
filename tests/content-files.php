<?php
spl_autoload_register(fn($c)=>require_once __DIR__.'/../core/'.$c.'.php');
function check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
$root=sys_get_temp_dir().'/wiki-files-'.bin2hex(random_bytes(6));mkdir($root.'/content',0775,true);
try {
 $files=new ContentFiles($root,fn($ns)=>$ns!=='private',new History($root.'/data/history',true));
 $files->mkdir('empty/nested');check(in_array('empty/nested',$files->folders(),true),'Empty folders listed');
 try{$files->mkdir('../outside');throw new LogicException('Unsafe folder accepted');}catch(RuntimeException $expected){}
 $raw="---\ntitle: 'Raw'\n---\n# Original\n";
 $a=$files->write('upload','test.md',$raw,'');check($a['raw']===$raw,'Exact raw upload');
 $b=$files->write('save','test.md',$raw.'Extra',$a['revision']);check($b['raw']===$raw.'Extra','Raw save');
 foreach([fn()=>$files->write('save','test.md','Lost',$a['revision']),fn()=>$files->write('upload','test.md','Overwrite',''),fn()=>$files->read('../secret.md'),fn()=>$files->write('upload','bad.php','Code',''),fn()=>$files->write('upload','private/secret.md','Private','')] as $action){try{$action();throw new LogicException('Unsafe action accepted');}catch(RuntimeException $expected){}}
 $c=$files->write('move','test.md','',$b['revision'],'docs/renamed.md');check(!is_file($root.'/content/test.md')&&$c['raw']===$b['raw'],'Move preserves bytes');
 check($files->listing()===['docs/renamed.md'],'List confined Markdown');
 echo "PASS content files: raw roundtrip, move, stale writes, overwrite, extension, traversal, permissions\n";
}finally{ $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($root); }
