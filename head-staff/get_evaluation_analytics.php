<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    $mode = $_GET['mode'] ?? 'all';
    $eventId = $_GET['event_id'] ?? null;
    
    // Base query for evaluations
    $evaluationQuery = "SELECT e.*, ev.title as event_title, ev.start_date, ev.end_date, 
                       sa.first_name, sa.last_name, sa.sr_code
                       FROM event_evaluations e
                       JOIN events ev ON e.event_id = ev.id
                       JOIN student_artists sa ON e.student_id = sa.id";
    
    $params = [];
    $whereConditions = [];
    
    if (($mode === 'specific' || $mode === 'individual') && $eventId) {
        $whereConditions[] = "e.event_id = ?";
        $params[] = $eventId;
    }
    
    if (!empty($whereConditions)) {
        $evaluationQuery .= " WHERE " . implode(' AND ', $whereConditions);
    }
    
    $evaluationQuery .= " ORDER BY e.submitted_at DESC";
    
    $stmt = $pdo->prepare($evaluationQuery);
    $stmt->execute($params);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all available events (with or without evaluations)
    $eventsQuery = "SELECT ev.id, ev.title, ev.location, ev.category, 
                    ev.start_date as event_date, ev.start_date,
                    DATE_FORMAT(ev.start_date, '%M %d, %Y') as formatted_date,
                    COUNT(e.id) as evaluation_count
                    FROM events ev
                    LEFT JOIN event_evaluations e ON ev.id = e.event_id
                    GROUP BY ev.id, ev.title, ev.location, ev.category, ev.start_date
                    ORDER BY ev.start_date DESC";
    $eventsStmt = $pdo->prepare($eventsQuery);
    $eventsStmt->execute();
    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $statistics = calculateStatistics($evaluations, $pdo, $eventId);
    
    // Get rating distribution
    $ratingDistribution = getRatingDistribution($evaluations);
    
    // Get question scores
    $questionScores = getQuestionScores($evaluations);
    
    // Get trends data
    $trends = getTrendsData($evaluations);
    
    // Generate insights
    $insights = generateInsights($evaluations, $statistics);
    
    // Get comments analysis
    $comments = getCommentsAnalysis($evaluations);
    
    echo json_encode([
        'success' => true,
        'evaluations' => $evaluations,
        'events' => $events,
        'statistics' => $statistics,
        'rating_distribution' => $ratingDistribution,
        'question_scores' => $questionScores,
        'trends' => $trends,
        'insights' => $insights,
        'comments' => $comments
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error loading evaluation analytics: ' . $e->getMessage()]);
}

function calculateStatistics($evaluations, $pdo, $eventId = null) {
    $totalEvaluations = count($evaluations);
    
    if ($totalEvaluations === 0) {
        return [
            'total_evaluations' => 0,
            'average_rating' => 0.0,
            'response_rate' => 0,
            'satisfaction_score' => 0
        ];
    }
    
    // Calculate average rating across all questions
    $totalRatings = 0;
    $ratingCount = 0;
    $satisfiedCount = 0;
    
    foreach ($evaluations as $evaluation) {
        $ratings = [
            $evaluation['q1_rating'], $evaluation['q2_rating'], $evaluation['q3_rating'],
            $evaluation['q4_rating'], $evaluation['q5_rating'], $evaluation['q6_rating'],
            $evaluation['q7_rating'], $evaluation['q8_rating'], $evaluation['q9_rating']
        ];
        
        foreach ($ratings as $rating) {
            if ($rating !== null) {
                $totalRatings += $rating;
                $ratingCount++;
                
                // Consider 4 and 5 as satisfied
                if ($rating >= 4) {
                    $satisfiedCount++;
                }
            }
        }
    }
    
    $averageRating = $ratingCount > 0 ? $totalRatings / $ratingCount : 0;
    $satisfactionScore = $ratingCount > 0 ? round(($satisfiedCount / $ratingCount) * 100) : 0;
    
    // Calculate response rate
    $responseRate = 0;
    if ($eventId) {
        // Get total participants for specific event
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_participants FROM event_participants WHERE event_id = ?");
        $stmt->execute([$eventId]);
        $totalParticipants = $stmt->fetchColumn();
        $responseRate = $totalParticipants > 0 ? round(($totalEvaluations / $totalParticipants) * 100) : 0;
    } else {
        // For all events, calculate average response rate
        $eventIds = array_unique(array_column($evaluations, 'event_id'));
        $totalResponseRates = 0;
        $eventCount = 0;
        
        foreach ($eventIds as $eId) {
            $eventEvaluations = array_filter($evaluations, function($e) use ($eId) {
                return $e['event_id'] == $eId;
            });
            $evalCount = count($eventEvaluations);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_participants FROM event_participants WHERE event_id = ?");
            $stmt->execute([$eId]);
            $totalParticipants = $stmt->fetchColumn();
            
            if ($totalParticipants > 0) {
                $totalResponseRates += ($evalCount / $totalParticipants) * 100;
                $eventCount++;
            }
        }
        
        $responseRate = $eventCount > 0 ? round($totalResponseRates / $eventCount) : 0;
    }
    
    return [
        'total_evaluations' => $totalEvaluations,
        'average_rating' => $averageRating,
        'response_rate' => $responseRate,
        'satisfaction_score' => $satisfactionScore
    ];
}

function getRatingDistribution($evaluations) {
    $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    
    foreach ($evaluations as $evaluation) {
        $ratings = [
            $evaluation['q1_rating'], $evaluation['q2_rating'], $evaluation['q3_rating'],
            $evaluation['q4_rating'], $evaluation['q5_rating'], $evaluation['q6_rating'],
            $evaluation['q7_rating'], $evaluation['q8_rating'], $evaluation['q9_rating']
        ];
        
        foreach ($ratings as $rating) {
            if ($rating !== null && isset($distribution[$rating])) {
                $distribution[$rating]++;
            }
        }
    }
    
    $result = [];
    foreach ($distribution as $rating => $count) {
        $result[] = ['rating' => $rating, 'count' => $count];
    }
    
    return $result;
}

function getQuestionScores($evaluations) {
    $questions = [
        'q1' => 'The goals of the presentation were clear.',
        'q2' => 'Effectiveness of the Activity.',
        'q3' => 'Methods and Procedure of the Activity.',
        'q4' => 'Time allotment of the activity.',
        'q5' => 'Relevance of the activity to University Vision, Mission and Objectives.',
        'q6' => 'Registration.',
        'q7' => 'Objectives of the activity were achieved.',
        'q8' => 'Venue.',
        'q9' => 'Technical Support Services.'
    ];
    
    $questionScores = [];
    
    foreach ($questions as $qCode => $qText) {
        $ratingColumn = $qCode . '_rating';
        $ratings = array_column($evaluations, $ratingColumn);
        $ratings = array_filter($ratings, function($r) { return $r !== null; });
        
        if (count($ratings) > 0) {
            $averageScore = array_sum($ratings) / count($ratings);
            $questionScores[] = [
                'question' => $qText,
                'average_score' => $averageScore,
                'response_count' => count($ratings)
            ];
        }
    }
    
    // Sort by average score descending
    usort($questionScores, function($a, $b) {
        return $b['average_score'] <=> $a['average_score'];
    });
    
    return $questionScores;
}

function getTrendsData($evaluations) {
    if (empty($evaluations)) {
        return [];
    }
    
    // Group evaluations by date
    $dateGroups = [];
    foreach ($evaluations as $evaluation) {
        $date = date('Y-m-d', strtotime($evaluation['start_date']));
        if (!isset($dateGroups[$date])) {
            $dateGroups[$date] = [];
        }
        $dateGroups[$date][] = $evaluation;
    }
    
    $trends = [];
    foreach ($dateGroups as $date => $dayEvaluations) {
        $totalRatings = 0;
        $ratingCount = 0;
        
        foreach ($dayEvaluations as $evaluation) {
            $ratings = [
                $evaluation['q1_rating'], $evaluation['q2_rating'], $evaluation['q3_rating'],
                $evaluation['q4_rating'], $evaluation['q5_rating'], $evaluation['q6_rating'],
                $evaluation['q7_rating'], $evaluation['q8_rating'], $evaluation['q9_rating']
            ];
            
            foreach ($ratings as $rating) {
                if ($rating !== null) {
                    $totalRatings += $rating;
                    $ratingCount++;
                }
            }
        }
        
        if ($ratingCount > 0) {
            $averageRating = $totalRatings / $ratingCount;
            $trends[] = [
                'date' => date('M j', strtotime($date)),
                'full_date' => $date,
                'average_rating' => $averageRating,
                'evaluation_count' => count($dayEvaluations)
            ];
        }
    }
    
    // Sort by date
    usort($trends, function($a, $b) {
        return strtotime($a['full_date']) <=> strtotime($b['full_date']);
    });
    
    return $trends;
}

function generateInsights($evaluations, $statistics) {
    $insights = [];
    
    // Overall performance insight
    $avgRating = $statistics['average_rating'];
    if ($avgRating >= 4.5) {
        $insights['overall'] = "Excellent performance! Events are consistently rated very highly with an average rating of " . number_format($avgRating, 1) . "/5.0. This indicates exceptional satisfaction among participants.";
    } elseif ($avgRating >= 4.0) {
        $insights['overall'] = "Strong performance with an average rating of " . number_format($avgRating, 1) . "/5.0. Events are well-received by participants with good satisfaction levels.";
    } elseif ($avgRating >= 3.5) {
        $insights['overall'] = "Moderate performance with an average rating of " . number_format($avgRating, 1) . "/5.0. There's room for improvement to enhance participant satisfaction.";
    } elseif ($avgRating >= 3.0) {
        $insights['overall'] = "Below average performance with a rating of " . number_format($avgRating, 1) . "/5.0. Significant improvements are needed to meet participant expectations.";
    } else {
        $insights['overall'] = "Performance needs urgent attention with a low average rating of " . number_format($avgRating, 1) . "/5.0. Major improvements are required across all aspects.";
    }
    
    // Response rate insight
    $responseRate = $statistics['response_rate'];
    if ($responseRate >= 70) {
        $insights['strengths'] = "Excellent response rate of {$responseRate}% indicates high participant engagement and willingness to provide feedback.";
    } elseif ($responseRate >= 50) {
        $insights['strengths'] = "Good response rate of {$responseRate}% shows solid participant engagement in the feedback process.";
    } else {
        $insights['improvements'] = "Low response rate of {$responseRate}% suggests need to improve feedback collection methods and participant engagement strategies.";
    }
    
    // Satisfaction score insight
    $satisfactionScore = $statistics['satisfaction_score'];
    if ($satisfactionScore >= 80) {
        if (!isset($insights['strengths'])) {
            $insights['strengths'] = "High satisfaction score of {$satisfactionScore}% indicates most participants are very pleased with the events.";
        } else {
            $insights['strengths'] .= " Additionally, the high satisfaction score of {$satisfactionScore}% shows excellent participant contentment.";
        }
    } elseif ($satisfactionScore >= 60) {
        $insights['trends'] = "Moderate satisfaction score of {$satisfactionScore}% suggests room for improvement in event quality and participant experience.";
    } else {
        if (!isset($insights['improvements'])) {
            $insights['improvements'] = "Low satisfaction score of {$satisfactionScore}% indicates significant improvements needed in event planning and execution.";
        } else {
            $insights['improvements'] .= " The low satisfaction score of {$satisfactionScore}% also highlights areas needing attention.";
        }
    }
    
    // Recommendations based on overall performance
    if ($avgRating < 4.0) {
        $insights['recommendations'] = "Focus on improving event organization, venue quality, and staff support. Consider gathering more detailed feedback to identify specific areas for enhancement.";
    } elseif ($responseRate < 50) {
        $insights['recommendations'] = "Implement strategies to increase feedback participation, such as incentives, simplified evaluation forms, or immediate post-event surveys.";
    } else {
        $insights['recommendations'] = "Continue maintaining high standards while exploring innovative ways to further enhance participant experience and engagement.";
    }
    
    return $insights;
}

function getCommentsAnalysis($evaluations) {
    $positiveComments = [];
    $improvementComments = [];
    
    foreach ($evaluations as $evaluation) {
        if (!empty($evaluation['comments'])) {
            $comment = [
                'text' => $evaluation['comments'],
                'event_title' => $evaluation['event_title'],
                'date' => date('M j, Y', strtotime($evaluation['start_date'])),
                'rating_context' => calculateCommentRating($evaluation)
            ];
            
            // Simple sentiment analysis based on average rating
            $avgRating = calculateCommentRating($evaluation);
            
            if ($avgRating >= 4.0) {
                $positiveComments[] = $comment;
            } elseif ($avgRating <= 3.0) {
                $improvementComments[] = $comment;
            }
        }
    }
    
    // Limit to most recent 10 comments each
    $positiveComments = array_slice($positiveComments, 0, 10);
    $improvementComments = array_slice($improvementComments, 0, 10);
    
    return [
        'positive' => $positiveComments,
        'improvement' => $improvementComments
    ];
}

function calculateCommentRating($evaluation) {
    $ratings = [
        $evaluation['q1_rating'], $evaluation['q2_rating'], $evaluation['q3_rating'],
        $evaluation['q4_rating'], $evaluation['q5_rating'], $evaluation['q6_rating'],
        $evaluation['q7_rating'], $evaluation['q8_rating'], $evaluation['q9_rating']
    ];
    
    $validRatings = array_filter($ratings, function($r) { return $r !== null; });
    return count($validRatings) > 0 ? array_sum($validRatings) / count($validRatings) : 0;
}
?>