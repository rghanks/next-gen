<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabReport;
use App\Domain\Lab\Services\ReportRenderer;
use App\Domain\Lab\States\LabState;
use App\Models\LabSetting;
use App\Models\User;
use Tests\TestCase;

class LabSignatureSettingsTest extends TestCase
{
    protected function tearDown(): void
    {
        LabSetting::current(true)->update([
            'print_with_signature' => true,
            'signature_position' => 'bottom_of_page',
            'signature_page_target' => 'all_pages',
            'signature_layout' => 'dual',
            'technician_signature_label' => 'Prepared By',
            'pathologist_signature_label' => 'Verified By',
            'signature_space_top' => 5,
            'signature_space_bottom' => 5,
            'signature_space_left' => 10,
            'signature_space_right' => 10,
        ]);

        parent::tearDown();
    }

    public function test_can_update_signature_settings_and_spacing(): void
    {
        $user = User::first();

        $response = $this->actingAs($user)->post(route('lab.settings.update'), [
            'lab_name' => 'Apex Clinical Diagnostics',
            'technician_name' => 'Rajesh Sharma',
            'technician_designation' => 'Senior Technologist',
            'technician_degree' => 'B.Sc MLT',
            'technician_signature_label' => 'Checked By',
            'pathologist_name' => 'Dr. A. K. Verma',
            'pathologist_designation' => 'Chief Pathologist',
            'pathologist_degree' => 'MD (Pathology)',
            'pathologist_signature_label' => 'Authorized Signatory',
            'print_with_signature' => '1',
            'signature_position' => 'after_report',
            'signature_page_target' => 'last_page_only',
            'signature_layout' => 'dual',
            'signature_height' => 42,
            'signature_space_top' => 8,
            'signature_space_bottom' => 6,
            'signature_space_left' => 12,
            'signature_space_right' => 12,
            'print_top_margin' => 35,
            'print_bottom_margin' => 20,
        ]);

        $response->assertRedirect(route('lab.settings.index'));

        $setting = LabSetting::current(true);
        $this->assertEquals('Checked By', $setting->technician_signature_label);
        $this->assertEquals('Authorized Signatory', $setting->pathologist_signature_label);
        $this->assertTrue($setting->print_with_signature);
        $this->assertEquals('after_report', $setting->signature_position);
        $this->assertEquals('last_page_only', $setting->signature_page_target);
        $this->assertEquals('dual', $setting->signature_layout);
        $this->assertEquals(42, $setting->signature_height);
        $this->assertEquals(8, $setting->signature_space_top);
        $this->assertEquals(6, $setting->signature_space_bottom);
        $this->assertEquals(12, $setting->signature_space_left);
        $this->assertEquals(12, $setting->signature_space_right);
        $this->assertTrue($setting->isSignatureAfterReport());
    }

    public function test_report_renders_with_signature_customizations(): void
    {
        $setting = LabSetting::current(true);
        $setting->update([
            'technician_name' => 'Anita MLT',
            'technician_designation' => 'Lab Technician',
            'technician_signature_label' => 'Sample Processed By',
            'pathologist_name' => 'Dr. Sonali Kalyan',
            'pathologist_designation' => 'Consultant Pathologist',
            'pathologist_signature_label' => 'Doctor In-Charge',
            'print_with_signature' => false, // manual stamp mode
            'signature_position' => 'after_report',
            'signature_space_top' => 15,
            'signature_space_bottom' => 10,
            'signature_space_left' => 14,
            'signature_space_right' => 14,
        ]);

        $order = LabOrder::first();
        if (!$order) {
            $order = LabOrder::create([
                'patient_id' => 1,
                'accession_no' => 'ACC-TEST-99',
                'status' => LabState::RELEASED,
                'priority' => 'routine',
            ]);
        } else {
            $order->status = LabState::RELEASED;
            $order->save();
        }

        $report = LabReport::firstOrCreate(
            ['lab_order_id' => $order->id, 'version' => 1],
            ['template' => 'standard', 'verified_by' => 1]
        );

        $renderer = app(ReportRenderer::class);
        $html = $renderer->renderHtml($order, $report);

        $this->assertStringContainsString('Sample Processed By', $html);
        $this->assertStringContainsString('Doctor In-Charge', $html);
        $this->assertStringContainsString('padding-top: 15mm;', $html);
        $this->assertStringContainsString('padding-left: 14mm;', $html);
        $this->assertStringContainsString('*** End of Report ***', $html);
    }
}
