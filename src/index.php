<?php
require __DIR__ . '/../vendor/autoload.php'; // Composer로 설치한 라이브러리를 불러옴

use Kreait\Firebase\Factory; // Firebase Factory 클래스를 불러옴

$factory = (new Factory) // Firebase Factory 인스턴스 생성 (Firebase 프로젝트 설정
    ->withServiceAccount(__DIR__ . '/../firebase_service_account.json') // 서비스 계정 키 파일 경로
    ->withDatabaseUri('https://phpfirebase-3e085-default-rtdb.firebaseio.com'); // Realtime Database URL

$realtimeDatabase = $factory->createDatabase(); // Realtime Database 인스턴스 생성

$requestUri = $_SERVER['REQUEST_URI']; // 요청 URI
$parsedUrl = parse_url($requestUri); // 요청 URI를 파싱 (쿼리 스트링을 제외한 경로만 추출)
$path = $parsedUrl['path']; // 경로만 추출

if ($path == '/rankings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
   // JSON 바디에서 데이터 읽어오기
    $json = file_get_contents('php://input');  // 요청 바디를 문자열로 읽음
    $data = json_decode($json, true);          // JSON 문자열을 PHP 배열로 디코딩

    $userId = $data['user_id'] ?? 'unknown';
    $score = $data['score'] ?? 0;
    $timestamp = time();

    // 기존 점수가 있는 경우, 더 높은 점수일 때만 갱신
    $existingScore = $realtimeDatabase->getReference('rankings/' . $userId . '/score')->getValue();
    if ($existingScore !== null && $existingScore <= $score) {
        $realtimeDatabase->getReference('rankings/' . $userId)->set([
            'score' => $score,
            'timestamp' => $timestamp
        ]);
        $message = 'Score updated.';
    } elseif ($existingScore === null) { // 기존 점수가 없는 경우, 새로 저장
        $realtimeDatabase->getReference('rankings/' . $userId)->set([
            'score' => $score,
            'timestamp' => $timestamp
        ]);
        $message = 'Score saved.';
    } else {
        $message = 'Score not saved.';
    }

    // JSON 형식으로 응답
    header('Content-Type: application/json');
    echo json_encode([
        'message' => $message,
        'data' => [
            'user_id' => $userId,
            'score' => $score
        ]
    ]);
    return;
} elseif ($path == '/rankings' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    //랭킹을 최대 8개 까지 1위 부터 8위까지 가져옴
    $rankings = $realtimeDatabase->getReference('rankings')
        ->orderByChild('score')
        ->limitToLast(8)
        ->getSnapshot()
        ->getValue();
    
    $rankings = array_reverse($rankings, true);
    header('Content-Type: application/json');
    echo json_encode($rankings);
} else {
    http_response_code(404);
    echo "Not found";
}
