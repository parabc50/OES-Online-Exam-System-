<?php
/**
 * Answer Similarity Checker
 * Detects potential cheating by comparing student answers for similarity
 * Usage: php check_answer_similarity.php [exam_id] [threshold]
 */

require_once __DIR__ . '/../config/db.php';

$exam_id = isset($argv[1]) ? intval($argv[1]) : 0;
$similarity_threshold = isset($argv[2]) ? floatval($argv[2]) : 0.80; // 80% similarity threshold

if ($exam_id <= 0) {
    echo "Usage: php check_answer_similarity.php [exam_id] [similarity_threshold(0-1)]\n";
    echo "Example: php check_answer_similarity.php 5 0.80\n";
    exit(1);
}

// Get all descriptive answers for this exam
$answers_query = "SELECT sa.answer_id, sa.result_id, r.student_id, u.first_name, u.last_name, 
                         q.question_id, q.question_text, sa.descriptive_answer
                  FROM student_answers sa
                  JOIN results r ON sa.result_id = r.result_id
                  JOIN users u ON r.student_id = u.user_id
                  JOIN questions q ON sa.question_id = q.question_id
                  WHERE r.exam_id = ? AND q.question_type = 'descriptive' AND sa.descriptive_answer IS NOT NULL
                  ORDER BY q.question_id";
$stmt = $conn->prepare($answers_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$answers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (count($answers) === 0) {
    echo "No descriptive answers found for exam ID $exam_id\n";
    exit(0);
}

// Group answers by question
$answers_by_question = [];
foreach ($answers as $answer) {
    $qid = $answer['question_id'];
    if (!isset($answers_by_question[$qid])) {
        $answers_by_question[$qid] = [];
    }
    $answers_by_question[$qid][] = $answer;
}

// Compare answers for each question
$flagged_pairs = [];
foreach ($answers_by_question as $question_id => $question_answers) {
    $question_text = $question_answers[0]['question_text'];
    
    for ($i = 0; $i < count($question_answers); $i++) {
        for ($j = $i + 1; $j < count($question_answers); $j++) {
            $answer1 = $question_answers[$i];
            $answer2 = $question_answers[$j];
            
            $similarity = calculateSimilarity($answer1['descriptive_answer'], $answer2['descriptive_answer']);
            
            if ($similarity >= $similarity_threshold) {
                $flagged_pairs[] = [
                    'student1' => $answer1['first_name'] . ' ' . $answer1['last_name'],
                    'student2' => $answer2['first_name'] . ' ' . $answer2['last_name'],
                    'question' => $question_text,
                    'similarity' => round($similarity * 100, 2),
                    'result_id_1' => $answer1['result_id'],
                    'result_id_2' => $answer2['result_id']
                ];
            }
        }
    }
}

// Output results
if (count($flagged_pairs) === 0) {
    echo "No high similarity answers detected (threshold: " . ($similarity_threshold * 100) . "%)\n";
} else {
    echo "Flagged Answer Pairs (Similarity >= " . ($similarity_threshold * 100) . "%)\n";
    echo "===================================================\n";
    foreach ($flagged_pairs as $pair) {
        echo "Question: " . $pair['question'] . "\n";
        echo "Student 1: " . $pair['student1'] . "\n";
        echo "Student 2: " . $pair['student2'] . "\n";
        echo "Similarity: " . $pair['similarity'] . "%\n";
        echo "---\n";
    }
}

exit(0);

/**
 * Calculate similarity between two strings using Levenshtein distance
 * @param string $str1
 * @param string $str2
 * @return float Similarity score 0-1
 */
function calculateSimilarity($str1, $str2) {
    // Normalize strings
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));
    
    // If both are empty or identical
    if ($str1 === $str2) {
        return 1.0;
    }
    if (empty($str1) || empty($str2)) {
        return 0.0;
    }
    
    // Calculate Levenshtein distance
    $distance = levenshtein($str1, $str2);
    $max_length = max(strlen($str1), strlen($str2));
    
    if ($max_length === 0) {
        return 1.0;
    }
    
    // Convert distance to similarity (0-1)
    $similarity = 1 - ($distance / $max_length);
    return max(0, min(1, $similarity)); // Ensure it's between 0 and 1
}
