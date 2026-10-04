<?php
/**
 * Purpose: Read CSV and DOCX content and map supported CSV headers for Smart Upload.
 * Included by: admin/smart_upload_process.php.
 * Inputs/outputs: Accepts validated temporary file paths; returns rows, text, or mapped datasets.
 * Dependencies: includes/functions.php and the ZipArchive extension for DOCX parsing.
 * Load order: Include after the upload endpoint validates extension, size, and file signature.
 */
require_once __DIR__.'/../functions.php';
/**
 * Read a CSV file as normalized header-keyed rows, skipping empty records.
 *
 * @param string $path Path to the validated CSV file.
 * @return array Rows keyed by normalized header names.
 * @side-effects Opens and closes the input file.
 */
function read_csv_rows(string $path):array{$h=fopen($path,'r');if(!$h)throw new RuntimeException('Could not open CSV.');$bom=fread($h,3);if($bom!=="\xEF\xBB\xBF")rewind($h);$head=fgetcsv($h);if($head===false){fclose($h);return [];} $head=array_map(fn($x)=>strtolower(trim((string)$x)),$head);$rows=[];while(($row=fgetcsv($h))!==false){if(!array_filter($row,fn($x)=>trim((string)$x)!==''))continue;$row=array_pad($row,count($head),null);$rows[]=array_combine($head,array_slice($row,0,count($head)));}fclose($h);return $rows;}
/**
 * Render parsed CSV rows as pipe-separated text for extraction review.
 *
 * @param string $path Path to the validated CSV file.
 * @return string Header and row values in extraction-review text form.
 */
function csv_text(string $path):string{$rows=read_csv_rows($path);if(!$rows)throw new RuntimeException('The CSV file appears to be empty.');$text=implode(' | ',array_keys($rows[0]))."\n";foreach($rows as $r)$text.=implode(' | ',array_map(fn($v)=>$v??'',$r))."\n";return $text;}
/**
 * Map recognized CSV headers to supported Smart Upload dataset collections.
 *
 * @param string $path Path to the validated CSV file.
 * @return array|null Mapped collections, or null when headers are not recognized.
 */
function smart_map_csv(string $path):?array{$rows=read_csv_rows($path);if(!$rows)return ['rankings'=>[],'ranking_breakdowns'=>[],'colleges'=>[],'programs'=>[],'accreditations'=>[]];$cols=array_keys($rows[0]);$templates=['rankings'=>['ranking_body_short_name','year','global_rank','ph_rank'],'colleges'=>['name','short_code','contribution_percent','year'],'programs'=>['name','college_short_code','national_rank','score']];$best=null;$score=0;foreach($templates as $type=>$req){$s=count(array_intersect($req,$cols));if($s>$score){$score=$s;$best=$type;}}if(!$best||$score<ceil(count($templates[$best])/2))return null;$out=['rankings'=>[],'ranking_breakdowns'=>[],'colleges'=>[],'programs'=>[],'accreditations'=>[]];foreach($rows as $r){if($best==='rankings')$out['rankings'][]=['ranking_body_short_name'=>$r['ranking_body_short_name']??'','year'=>$r['year']??null,'category'=>$r['category']??null,'global_rank'=>$r['global_rank']??null,'ph_rank'=>$r['ph_rank']??null,'note'=>$r['note']??''];elseif($best==='colleges')$out['colleges'][]=['name'=>$r['name']??'','short_code'=>$r['short_code']??'','contribution_percent'=>$r['contribution_percent']??null,'year'=>$r['year']??null];else$out['programs'][]=['name'=>$r['name']??'','college_short_code'=>$r['college_short_code']??'','national_rank'=>$r['national_rank']??null,'score'=>$r['score']??null,'movement'=>$r['movement']??0,'year'=>$r['year']??null];}return $out;}
/**
 * Extract readable text from the main document XML inside a DOCX package.
 *
 * @param string $path Path to the validated DOCX package.
 * @return string Plain text extracted from the main document body.
 * @side-effects Opens and closes the package archive.
 */
function docx_text(string $path):string{$z=new ZipArchive();if($z->open($path)!==true)throw new RuntimeException('Could not open DOCX.');$xml=$z->getFromName('word/document.xml');$z->close();if(!$xml)throw new RuntimeException('No readable Word document content found.');$xml=preg_replace('/<w:tab[^>]*\/>/',' | ',$xml);$xml=preg_replace('/<w:(br|p)[^>]*\/>/',"\n",$xml);$xml=strip_tags($xml);$xml=html_entity_decode($xml,ENT_QUOTES|ENT_XML1,'UTF-8');$xml=preg_replace('/\s+/',' ',$xml);return trim($xml);}
