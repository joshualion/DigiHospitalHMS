<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiology_modalities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('radiology_studies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('radiology_modality_id')->constrained()->restrictOnDelete();
            $table->foreignId('billable_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('preparation_acknowledgements')->nullable();
            $table->json('safety_screening_acknowledgements')->nullable();
            $table->boolean('requires_professional_validation')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('radiology_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ordering_clinician_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_number')->unique();
            $table->string('accession_number')->unique();
            $table->string('status')->default('ordered')->index();
            $table->string('priority')->default('routine');
            $table->text('clinical_indication');
            $table->json('preparation_acknowledged')->nullable();
            $table->json('safety_screening_acknowledged')->nullable();
            $table->timestamp('ordered_at');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('room')->nullable();
            $table->string('equipment')->nullable();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->text('performance_notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hospital_id', 'facility_id', 'status', 'scheduled_at'], 'rad_req_schedule_idx');
        });

        Schema::create('radiology_request_studies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('radiology_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('radiology_study_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('study_code');
            $table->string('study_name');
            $table->timestamps();
        });

        Schema::create('radiology_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('radiology_request_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft')->index();
            $table->text('findings')->nullable();
            $table->text('impression')->nullable();
            $table->text('recommendations')->nullable();
            $table->foreignId('reporting_radiologist_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->boolean('has_critical_finding')->default(false);
            $table->text('critical_finding_notes')->nullable();
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('entered_at');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('radiology_critical_communications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('radiology_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('communicated_by')->constrained('users')->restrictOnDelete();
            $table->string('communicated_to');
            $table->string('method');
            $table->text('notes');
            $table->timestamp('communicated_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('escalation_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('radiology_report_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('radiology_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->text('content');
            $table->timestamp('authored_at');
            $table->timestamps();
        });

        Schema::create('radiology_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('radiology_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('radiology_report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type');
            $table->string('extension', 12);
            $table->unsignedBigInteger('size_bytes');
            $table->string('scan_status')->default('quarantined')->index();
            $table->string('status')->default('active')->index();
            $table->timestamp('cleared_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->text('retirement_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('radiology_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('subject');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_events');
        Schema::dropIfExists('radiology_attachments');
        Schema::dropIfExists('radiology_report_amendments');
        Schema::dropIfExists('radiology_critical_communications');
        Schema::dropIfExists('radiology_reports');
        Schema::dropIfExists('radiology_request_studies');
        Schema::dropIfExists('radiology_requests');
        Schema::dropIfExists('radiology_studies');
        Schema::dropIfExists('radiology_modalities');
    }
};
