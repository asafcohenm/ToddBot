<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$url = "https://todoist.com/API/v6/sync";
// $url = "https://todoist.com/API/v6/get_project";

$token = '481a5d08e58d901877761a9e73d1b18e8e0052bb';
// $project_id = '171571807';
// $content = 'Gussie ophalen van school';
$post_data = [
    'token' => $token,
    'seq_no' => 0,
    'resource_types' => '["items"]'
    // 'project_id' => $project_id
    // 'commands' => 
    //     '[{"type": "item_add", ' .
    //     '"temp_id": "43f7ed23-a038-46b5-b2c9-4abda9097ffa", ' .
    //     '"uuid": "997d4b43-55f1-48a9-9e66-de5785dfd41b", ' . 
    //     '"args": {"content":"'. $content . '", "project_id":'.$project_id.'}}]'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
$output = curl_exec($ch);
curl_close($ch);
echo '<pre>';
$json = json_decode($output, true);
// var_dump($json);
echo '</pre>';




foreach ($json['Items'] as $key => $value) {
		$items[] = $value;
	}

// foreach ($json['Labels'] as $key => $value) {
// 		$labels[] = $value;
// 	}

// foreach ($json['Filters'] as $key => $value) {
// 		$filters[] = $value;
// 	}

// foreach ($json['Projects'] as $key => $value) {
// 		$projects[] = $value;
// 	}


// echo '<pre>';
//  var_dump($items);
// echo '</pre>';

usort($items, function($a, $b) {
    return strtotime($a['date_added']) - strtotime($b['date_added']);
});

echo '<h2>Uncompleted tasks</h2>';
foreach ($items as $key => $value) {
		echo $value['content'] . '(' . strtotime($value['date_added']) . ')';
		echo '<br/>';
}

// echo '<h2>Filters</h2>';
// foreach ($filters as $key => $value) {
// 		echo $value['name'];
// 		echo '<br/>';
// }

// echo '<h2>Projects</h2>';
// foreach ($projects as $key => $value) {
// 		echo $value['name'];
// 		echo '<br/>';
//}

// echo '<h2>Labels</h2>';
// foreach ($labels as $key => $value) {
// 		echo $value['name'];
// 		echo '<br/>';
// }