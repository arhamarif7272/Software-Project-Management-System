<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityDependency;
use App\Models\ChangeRequest;
use App\Models\ChecklistItem;
use App\Models\CostEntry;
use App\Models\Defect;
use App\Models\InspectionResult;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\QualityChecklist;
use App\Models\Requirement;
use App\Models\Risk;
use App\Models\RiskResponse;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\WbsItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. User
        $user = User::firstOrCreate(
            ['email' => 'pm@healthcare.com'],
            ['name' => 'Project Manager', 'password' => Hash::make('password123')]
        );

        // 2. Project (16 weeks, PKR 5,000,000, starts 8 weeks ago)
        $startDate = now()->subWeeks(8);
        $project = Project::create([
            'name' => 'Smart Healthcare PMS (5 Hospitals)',
            'description' => 'Manage appointments, lab reports, doctor schedules, online consultations, and billing for 5 hospitals.',
            'objectives' => 'Deliver a unified healthcare management platform.',
            'scope_boundaries' => 'In scope: Core modules for 5 hospitals. Out of scope: Hardware procurement.',
            'approved_budget' => 5000000,
            'current_budget' => 5000000,
            'start_date' => $startDate->toDateString(),
            'approved_deadline' => $startDate->copy()->addWeeks(16)->toDateString(),
            'current_deadline' => $startDate->copy()->addWeeks(16)->toDateString(),
            'status' => 'executing',
            'charter_approved' => true,
            'charter_approved_at' => $startDate->copy()->addDays(3),
            'created_by' => $user->id,
        ]);

        $project->plan()->create([
            'scope_statement' => 'Core healthcare modules.',
            'schedule_approach' => 'Agile sprints with weekly reviews.',
            'cost_approach' => 'Fixed budget with change control.',
            'quality_approach' => 'Automated testing & UAT with hospital staff.',
            'responsibilities' => 'PM: Charter & integration; Backend: APIs; Frontend: UI; DB: Schema; QA: Testing.',
        ]);

        // 3. Team Members (7 people, exactly as per case study)
        $team = [
            ['name' => 'Ali (PM)', 'role' => 'project_manager', 'daily_rate' => 15000],
            ['name' => 'Sara (Backend 1)', 'role' => 'backend_developer', 'daily_rate' => 8000],
            ['name' => 'Bilal (Backend 2)', 'role' => 'backend_developer', 'daily_rate' => 8000],
            ['name' => 'Zoya (Frontend 1)', 'role' => 'frontend_developer', 'daily_rate' => 7000],
            ['name' => 'Omar (Frontend 2)', 'role' => 'frontend_developer', 'daily_rate' => 7000],
            ['name' => 'Tariq (DB)', 'role' => 'database_developer', 'daily_rate' => 9000],
            ['name' => 'Nadia (QA)', 'role' => 'qa_engineer', 'daily_rate' => 6000],
        ];
        foreach ($team as $t) {
            $project->teamMembers()->create($t + ['is_active' => true]);
        }

        $pm = $project->teamMembers()->where('role', 'project_manager')->first();
        $backend1 = $project->teamMembers()->where('role', 'backend_developer')->first();
        $backend2 = $project->teamMembers()->where('role', 'backend_developer')->skip(1)->first();
        $frontend1 = $project->teamMembers()->where('role', 'frontend_developer')->first();
        $db = $project->teamMembers()->where('role', 'database_developer')->first();
        $qa = $project->teamMembers()->where('role', 'qa_engineer')->first();

        // 4. Requirements (7 core requirements)
        $reqs = [
            ['code' => 'REQ-001', 'title' => 'Patient Appointments', 'type' => 'functional', 'priority' => 'critical', 'status' => 'approved', 'in_scope' => true],
            ['code' => 'REQ-002', 'title' => 'Laboratory Reports', 'type' => 'functional', 'priority' => 'high', 'status' => 'approved', 'in_scope' => true],
            ['code' => 'REQ-003', 'title' => 'Doctor Schedules', 'type' => 'functional', 'priority' => 'high', 'status' => 'approved', 'in_scope' => true],
            ['code' => 'REQ-004', 'title' => 'Online Consultations', 'type' => 'functional', 'priority' => 'high', 'status' => 'approved', 'in_scope' => true],
            ['code' => 'REQ-005', 'title' => 'Hospital Billing', 'type' => 'functional', 'priority' => 'critical', 'status' => 'approved', 'in_scope' => true],
            ['code' => 'REQ-006', 'title' => 'AI Preliminary Diagnosis', 'type' => 'functional', 'priority' => 'high', 'status' => 'approved', 'in_scope' => true],
            ['code' => 'REQ-007', 'title' => 'Patient Mobile App', 'type' => 'functional', 'priority' => 'high', 'status' => 'approved', 'in_scope' => true],
        ];
        foreach ($reqs as $r) {
            $project->requirements()->create($r);
        }

        // 5. Milestones
        $project->milestones()->create(['title' => 'Database Ready', 'due_date' => $startDate->copy()->addWeeks(2)->toDateString(), 'status' => 'achieved', 'completed_date' => $startDate->copy()->addWeeks(2)->toDateString(), 'responsible_id' => $db->id]);
        $project->milestones()->create(['title' => 'Backend APIs Complete', 'due_date' => $startDate->copy()->addWeeks(10)->toDateString(), 'status' => 'pending', 'responsible_id' => $backend1->id]);
        $project->milestones()->create(['title' => 'Core Modules Live', 'due_date' => $startDate->copy()->addWeeks(12)->toDateString(), 'status' => 'pending', 'responsible_id' => $pm->id]);
        $project->milestones()->create(['title' => 'Testing Complete', 'due_date' => $startDate->copy()->addWeeks(15)->toDateString(), 'status' => 'pending', 'responsible_id' => $qa->id]);

        // 6. WBS + Activities + Costs + Dependencies
        $root = WbsItem::create(['project_id' => $project->id, 'wbs_code' => '1', 'name' => 'Smart Healthcare System', 'level_type' => 'project', 'sort_order' => 0]);
        $phase1 = WbsItem::create(['project_id' => $project->id, 'parent_id' => $root->id, 'wbs_code' => '1.1', 'name' => 'Phase 1: Design & Architecture', 'level_type' => 'phase', 'sort_order' => 0]);
        $phase2 = WbsItem::create(['project_id' => $project->id, 'parent_id' => $root->id, 'wbs_code' => '1.2', 'name' => 'Phase 2: Core Development', 'level_type' => 'phase', 'sort_order' => 1]);
        $phase3 = WbsItem::create(['project_id' => $project->id, 'parent_id' => $root->id, 'wbs_code' => '1.3', 'name' => 'Phase 3: Change Requests & Testing', 'level_type' => 'phase', 'sort_order' => 2]);
        $delAppointments = WbsItem::create(['project_id' => $project->id, 'parent_id' => $phase2->id, 'requirement_id' => 1, 'wbs_code' => '1.2.1', 'name' => 'Appointments System', 'level_type' => 'deliverable', 'sort_order' => 0]);
        $delBilling = WbsItem::create(['project_id' => $project->id, 'parent_id' => $phase2->id, 'requirement_id' => 5, 'wbs_code' => '1.2.2', 'name' => 'Billing System', 'level_type' => 'deliverable', 'sort_order' => 1]);
        $delAI = WbsItem::create(['project_id' => $project->id, 'parent_id' => $phase3->id, 'requirement_id' => 6, 'wbs_code' => '1.3.1', 'name' => 'AI Diagnosis Module', 'level_type' => 'deliverable', 'sort_order' => 0]);
        $delMobile = WbsItem::create(['project_id' => $project->id, 'parent_id' => $phase3->id, 'requirement_id' => 7, 'wbs_code' => '1.3.2', 'name' => 'Patient Mobile App', 'level_type' => 'deliverable', 'sort_order' => 1]);

        $activities = [
            ['code' => 'A-001', 'name' => 'Design Database Schema', 'duration_days' => 14, 'assigned_to' => $db->id, 'wbs_item_id' => $phase1->id, 'status' => 'completed', 'percent_complete' => 100, 'actual_start' => $startDate->toDateString(), 'actual_end' => $startDate->copy()->addDays(14)->toDateString(), 'estimated_cost' => 126000],
            ['code' => 'A-002', 'name' => 'Setup Project Architecture', 'duration_days' => 7, 'assigned_to' => $pm->id, 'wbs_item_id' => $phase1->id, 'status' => 'completed', 'percent_complete' => 100, 'actual_start' => $startDate->copy()->addDays(15)->toDateString(), 'actual_end' => $startDate->copy()->addDays(21)->toDateString(), 'estimated_cost' => 105000],
            ['code' => 'A-003', 'name' => 'Develop Appointment APIs', 'duration_days' => 21, 'assigned_to' => $backend1->id, 'wbs_item_id' => $delAppointments->id, 'status' => 'completed', 'percent_complete' => 100, 'actual_start' => $startDate->copy()->addDays(22)->toDateString(), 'actual_end' => $startDate->copy()->addDays(42)->toDateString(), 'estimated_cost' => 168000],
            ['code' => 'A-004', 'name' => 'Develop Billing APIs', 'duration_days' => 21, 'assigned_to' => $backend2->id, 'wbs_item_id' => $delBilling->id, 'status' => 'in_progress', 'percent_complete' => 60, 'actual_start' => $startDate->copy()->addDays(22)->toDateString(), 'estimated_cost' => 168000],
            ['code' => 'A-005', 'name' => 'Frontend UI Design', 'duration_days' => 14, 'assigned_to' => $frontend1->id, 'wbs_item_id' => $phase1->id, 'status' => 'completed', 'percent_complete' => 100, 'actual_start' => $startDate->copy()->addDays(22)->toDateString(), 'actual_end' => $startDate->copy()->addDays(35)->toDateString(), 'estimated_cost' => 98000],
            ['code' => 'A-006', 'name' => 'Appointment UI Screens', 'duration_days' => 14, 'assigned_to' => $frontend1->id, 'wbs_item_id' => $delAppointments->id, 'status' => 'in_progress', 'percent_complete' => 80, 'actual_start' => $startDate->copy()->addDays(36)->toDateString(), 'estimated_cost' => 98000],
            ['code' => 'A-007', 'name' => 'Billing UI Screens', 'duration_days' => 14, 'assigned_to' => $frontend1->id, 'wbs_item_id' => $delBilling->id, 'status' => 'not_started', 'percent_complete' => 0, 'estimated_cost' => 98000],
            ['code' => 'A-008', 'name' => 'AI Model Prototype', 'duration_days' => 30, 'assigned_to' => $backend2->id, 'wbs_item_id' => $delAI->id, 'status' => 'in_progress', 'percent_complete' => 30, 'actual_start' => $startDate->copy()->addDays(43)->toDateString(), 'estimated_cost' => 240000],
            ['code' => 'A-009', 'name' => 'Mobile App Development', 'duration_days' => 45, 'assigned_to' => $frontend1->id, 'wbs_item_id' => $delMobile->id, 'status' => 'not_started', 'percent_complete' => 0, 'estimated_cost' => 315000],
            ['code' => 'A-010', 'name' => 'QA Integration Testing', 'duration_days' => 21, 'assigned_to' => $qa->id, 'wbs_item_id' => $phase3->id, 'status' => 'not_started', 'percent_complete' => 0, 'estimated_cost' => 126000],
            ['code' => 'A-011', 'name' => 'User Acceptance Testing', 'duration_days' => 14, 'assigned_to' => $qa->id, 'wbs_item_id' => $phase3->id, 'status' => 'not_started', 'percent_complete' => 0, 'estimated_cost' => 84000],
            ['code' => 'A-012', 'name' => 'Deployment & Handover', 'duration_days' => 7, 'assigned_to' => $pm->id, 'wbs_item_id' => $phase3->id, 'status' => 'not_started', 'percent_complete' => 0, 'estimated_cost' => 105000],
        ];

        $actModels = [];
        foreach ($activities as $a) {
            $actModels[$a['code']] = Activity::create($a + ['project_id' => $project->id]);
        }

        // Dependencies
        ActivityDependency::create(['activity_id' => $actModels['A-002']->id, 'predecessor_id' => $actModels['A-001']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-003']->id, 'predecessor_id' => $actModels['A-002']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-004']->id, 'predecessor_id' => $actModels['A-002']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-005']->id, 'predecessor_id' => $actModels['A-001']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-006']->id, 'predecessor_id' => $actModels['A-003']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-007']->id, 'predecessor_id' => $actModels['A-004']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-008']->id, 'predecessor_id' => $actModels['A-005']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-009']->id, 'predecessor_id' => $actModels['A-008']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-010']->id, 'predecessor_id' => $actModels['A-006']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-010']->id, 'predecessor_id' => $actModels['A-007']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-010']->id, 'predecessor_id' => $actModels['A-009']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-011']->id, 'predecessor_id' => $actModels['A-010']->id, 'dependency_type' => 'FS']);
        ActivityDependency::create(['activity_id' => $actModels['A-012']->id, 'predecessor_id' => $actModels['A-011']->id, 'dependency_type' => 'FS']);

        // 7. Cost Entries (Actuals)
        $project->costEntries()->create(['entry_date' => $startDate->copy()->addDays(5)->toDateString(), 'category' => 'labor', 'description' => 'DB Design Week 1', 'amount' => 63000, 'activity_id' => $actModels['A-001']->id]);
        $project->costEntries()->create(['entry_date' => $startDate->copy()->addDays(12)->toDateString(), 'category' => 'labor', 'description' => 'DB Design Week 2', 'amount' => 63000, 'activity_id' => $actModels['A-001']->id]);
        $project->costEntries()->create(['entry_date' => $startDate->copy()->addDays(20)->toDateString(), 'category' => 'labor', 'description' => 'Architecture Setup', 'amount' => 105000, 'activity_id' => $actModels['A-002']->id]);
        $project->costEntries()->create(['entry_date' => $startDate->copy()->addDays(30)->toDateString(), 'category' => 'labor', 'description' => 'Appointment APIs Sprint 1', 'amount' => 84000, 'activity_id' => $actModels['A-003']->id]);
        $project->costEntries()->create(['entry_date' => $startDate->copy()->addDays(40)->toDateString(), 'category' => 'labor', 'description' => 'Appointment APIs Sprint 2', 'amount' => 84000, 'activity_id' => $actModels['A-003']->id]);
        $project->costEntries()->create(['entry_date' => $startDate->copy()->addDays(42)->toDateString(), 'category' => 'infrastructure', 'description' => 'Server Setup', 'amount' => 50000]);
        $project->costEntries()->create(['entry_date' => $startDate->copy()->addDays(50)->toDateString(), 'category' => 'labor', 'description' => 'Billing APIs Sprint 1', 'amount' => 84000, 'activity_id' => $actModels['A-004']->id]);
        $project->costEntries()->create(['entry_date' => $startDate->copy()->addDays(52)->toDateString(), 'category' => 'labor', 'description' => 'Frontend UI Design', 'amount' => 98000, 'activity_id' => $actModels['A-005']->id]);
        $project->costEntries()->create(['entry_date' => $startDate->copy()->addDays(58)->toDateString(), 'category' => 'testing', 'description' => 'API Testing Tools License', 'amount' => 25000]);

        // 8. Change Request (AI + Mobile)
        $cr = $project->changeRequests()->create([
            'code' => 'CR-001',
            'title' => 'Add AI Preliminary Diagnosis & Mobile App',
            'description' => 'Hospital management requested an AI diagnosis module and a mobile application for patients.',
            'justification' => 'To improve patient reach and preliminary care.',
            'requested_by' => 'Hospital Board',
            'cost_impact' => 800000,
            'schedule_impact_days' => 21,
            'status' => 'approved',
            'decided_by' => $user->id,
            'decided_at' => $startDate->copy()->addDays(40),
            'applied_at' => $startDate->copy()->addDays(40),
            'budget_before' => 5000000, 'budget_after' => 5800000,
            'deadline_before' => $startDate->copy()->addWeeks(16)->toDateString(), 'deadline_after' => $startDate->copy()->addWeeks(19)->toDateString(),
        ]);
        $project->update(['current_budget' => 5800000, 'current_deadline' => $startDate->copy()->addWeeks(19)->toDateString()]);
        $cr->requirements()->attach([6, 7]);

        // 9. Defects (Critical from testing)
        $project->defects()->create([
            'code' => 'DEF-001', 'title' => 'Appointment scheduling crash on double booking', 'severity' => 'critical', 'status' => 'in_progress',
            'activity_id' => $actModels['A-003']->id, 'assigned_to' => $backend1->id, 'reported_by' => $qa->id, 'reported_on' => now()->subDays(3)->toDateString(),
        ]);
        $project->defects()->create([
            'code' => 'DEF-002', 'title' => 'Patient record data leak in API', 'severity' => 'critical', 'status' => 'open',
            'activity_id' => $actModels['A-003']->id, 'assigned_to' => $backend2->id, 'reported_by' => $qa->id, 'reported_on' => now()->subDays(1)->toDateString(),
        ]);
        $project->defects()->create([
            'code' => 'DEF-003', 'title' => 'UI misaligned on mobile', 'severity' => 'medium', 'status' => 'resolved', 'resolved_on' => now()->subDays(2)->toDateString(),
            'resolution_notes' => 'Fixed CSS flexbox issue', 'assigned_to' => $frontend1->id, 'reported_by' => $qa->id, 'reported_on' => now()->subDays(5)->toDateString(),
        ]);

        // 10. Quality Checklist
        $checklist = QualityChecklist::create(['project_id' => $project->id, 'wbs_item_id' => $delAppointments->id, 'name' => 'Appointment Module QA Checklist']);
        $items = [
            ['description' => 'Patient can book a new appointment', 'sort_order' => 0],
            ['description' => 'Doctor can view daily schedule', 'sort_order' => 1],
            ['description' => 'System prevents double booking', 'sort_order' => 2],
            ['description' => 'Email confirmation is sent to patient', 'sort_order' => 3],
        ];
        foreach ($items as $i) {
            $item = ChecklistItem::create(['checklist_id' => $checklist->id] + $i);
            if ($i['sort_order'] < 3) {
                InspectionResult::create(['checklist_item_id' => $item->id, 'inspected_by' => $qa->id, 'inspection_type' => 'unit_test', 'result' => 'pass', 'inspected_on' => now()->subDays(4)->toDateString(), 'notes' => 'Passed automated tests.']);
            } else {
                InspectionResult::create(['checklist_item_id' => $item->id, 'inspected_by' => $qa->id, 'inspection_type' => 'system_test', 'result' => 'fail', 'inspected_on' => now()->subDays(1)->toDateString(), 'notes' => 'Email gateway timed out during load testing.']);
            }
        }

        // 11. Risks (Prioritization Matrix + PDPC)
        $risk1 = Risk::create(['project_id' => $project->id, 'code' => 'RSK-001', 'title' => 'AI model accuracy below 80%', 'description' => 'The AI diagnosis module may fail to meet clinical accuracy standards.', 'category' => 'technical', 'likelihood' => 4, 'impact' => 5, 'level' => 'extreme', 'status' => 'mitigating', 'owner_id' => $backend2->id]);
        $risk2 = Risk::create(['project_id' => $project->id, 'code' => 'RSK-002', 'title' => 'Mobile app rejection by App Store', 'description' => 'Apple may reject the app for HIPAA compliance issues.', 'category' => 'external', 'likelihood' => 3, 'impact' => 4, 'level' => 'high', 'status' => 'identified', 'owner_id' => $frontend1->id]);
        $risk3 = Risk::create(['project_id' => $project->id, 'code' => 'RSK-003', 'title' => 'Data migration delays from legacy system', 'description' => 'Hospital legacy records may take longer to migrate.', 'category' => 'schedule', 'likelihood' => 3, 'impact' => 3, 'level' => 'medium', 'status' => 'identified', 'owner_id' => $db->id]);

        // PDPC for Risk 1
        $prob1 = RiskResponse::create(['risk_id' => $risk1->id, 'node_type' => 'potential_problem', 'description' => 'AI model fails clinical validation', 'owner_id' => $backend2->id, 'status' => 'planned']);
        $prevent1 = RiskResponse::create(['risk_id' => $risk1->id, 'parent_id' => $prob1->id, 'node_type' => 'preventive_action', 'description' => 'Hire a medical AI consultant to review training data.', 'owner_id' => $pm->id, 'status' => 'in_progress']);
        RiskResponse::create(['risk_id' => $risk1->id, 'parent_id' => $prevent1->id, 'node_type' => 'contingency', 'description' => 'Fallback to rule-based diagnosis if AI accuracy < 75%.', 'owner_id' => $pm->id, 'status' => 'planned']);
    }
}