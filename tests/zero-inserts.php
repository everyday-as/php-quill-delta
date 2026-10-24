<?php

use Everyday\QuillDelta\Delta;
use Everyday\QuillDelta\DeltaOp;

// Load this checkout with Composer dependencies, optionally supplied by the caller.
$loader=require ($argv[1] ?? dirname(__DIR__) . '/vendor/autoload.php');
$loader->addPsr4('Everyday\\QuillDelta\\', dirname(__DIR__) . '/src/', true);

// Check that zero is content while genuinely empty operations remain removable.
$cases=[
	'zero'            => [DeltaOp::text('0'), false],
	'empty text'      => [DeltaOp::text(''), true],
	'empty embed'     => [new DeltaOp([]), true],
	'formatted empty' => [DeltaOp::text('', ['bold' => true]), false],
	'nonempty embed'  => [DeltaOp::embed('image', 'image.png'), false]
];
foreach ($cases as $label => [$op, $expected]) {
	// Reject a wrong no-op classification before testing compaction.
	if ($op->isNoOp()!==$expected) {
		throw new RuntimeException($label . ': incorrect no-op classification.');
	}
}

// Exercise compaction with zero followed by text, formatting, and a terminator.
$sequences=[
	'zero before text'  => [DeltaOp::text('0'), DeltaOp::text(' items'), DeltaOp::text("\n")],
	'zero before bold'  => [DeltaOp::text('0'), DeltaOp::text(' items', ['bold' => true]), DeltaOp::text("\n")],
	'zero only'         => [DeltaOp::text('0'), DeltaOp::text("\n")],
	'empty before zero' => [DeltaOp::text(''), DeltaOp::text('0'), DeltaOp::text("\n")]
];
foreach ($sequences as $label => $ops) {
	// Verify that serialization preserves content through all compaction passes.
	$delta=new Delta($ops);
	$serialized=$delta->toArray();
	$plain='';
	foreach ($serialized['ops'] as $op) {
		$plain.=$op['insert'];
	}
	$expected=(str_contains($label, 'before text') || str_contains($label, 'before bold') ? "0 items\n" : "0\n");
	if ($plain!==$expected) {
		throw new RuntimeException($label . ': ' . json_encode($serialized));
	}
}
echo "Zero insert regression checks passed.\n";