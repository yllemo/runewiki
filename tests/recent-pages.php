<?php
spl_autoload_register(fn($c)=>require_once __DIR__.'/../core/'.$c.'.php');
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$dir=sys_get_temp_dir().'/recent-'.bin2hex(random_bytes(5));mkdir($dir);
try {
 file_put_contents($dir.'/old.md',"---\nupdated: 2026-01-01\n---\n# Old");
 file_put_contents($dir.'/new.md',"---\nupdated: 2026-10-01T12:00:00Z\n---\n# New");
 file_put_contents($dir.'/secret.md',"---\nrequire_login: true\nupdated: 2026-11-01\n---\n# Secret");
 file_put_contents($dir.'/denied.md',"# Denied");
 $pages=new PageLoader($dir,fn($id)=>$id->id()!=='denied');
 $parser=new Parser([], $pages);
 $html=$parser->toHtml("Intro\n{{recent#1}}");
 check(str_contains($html,'href="/new"')&&!str_contains($html,'href="/old"'),'Sorted and limited');
 check(!str_contains($html,'<time')&&!str_contains($html,'2026-10-01'),'Recent list shows links without dates');
 check(!str_contains($html,'Secret')&&!str_contains($html,'Denied'),'Protected pages excluded before limit');
 check(str_contains($parser->toHtml('{{recent}}'),'href="/old"'),'Default count');
 check(!str_contains($parser->toHtml("```text\n{{recent#1}}\n```"),'wiki-recent'),'Code examples stay literal');
 $member=new Parser([], $pages, null, null, fn()=>true);
 check(str_contains($member->toHtml('{{recent#1}}'),'Secret'),'Member sees protected page');
 echo "PASS recent pages order, count, authorization and literal code\n";
}finally{foreach(glob($dir.'/*.md') as $path)unlink($path);rmdir($dir);}
