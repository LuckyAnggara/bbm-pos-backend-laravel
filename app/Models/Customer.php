<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'branch_id',
        'notes',
        // Customer Classification
        'customer_type',
        'customer_tier',
        'company_name',
        'tax_id',
        'business_type',
        // Credit Management
        'credit_limit',
        'payment_terms_days',
        'credit_status',
        // Loyalty & Analytics
        'loyalty_points',
        'total_spent',
        'total_transactions',
        'last_purchase_date',
        // Preferences
        'preferences',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'credit_limit' => 'decimal:2',
        'loyalty_points' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'total_transactions' => 'integer',
        'payment_terms_days' => 'integer',
        'last_purchase_date' => 'datetime',
        'preferences' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Customer types
     */
    public const CUSTOMER_TYPES = [
        'individual' => 'Individual/Personal',
        'business' => 'Business/Corporate',
    ];

    /**
     * Customer tiers
     */
    public const CUSTOMER_TIERS = [
        'regular' => 'Regular',
        'silver' => 'Silver',
        'gold' => 'Gold',
        'platinum' => 'Platinum',
    ];

    /**
     * Credit status options
     */
    public const CREDIT_STATUSES = [
        'active' => 'Active',
        'suspended' => 'Suspended',
        'blocked' => 'Blocked',
    ];

    /**
     * Get the branch where the customer is registered.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get all of the sales for the customer.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Get customer type label
     */
    public function getCustomerTypeLabel(): string
    {
        return self::CUSTOMER_TYPES[$this->customer_type] ?? $this->customer_type;
    }

    /**
     * Get customer tier label
     */
    public function getCustomerTierLabel(): string
    {
        return self::CUSTOMER_TIERS[$this->customer_tier] ?? $this->customer_tier;
    }

    /**
     * Get credit status label
     */
    public function getCreditStatusLabel(): string
    {
        return self::CREDIT_STATUSES[$this->credit_status] ?? $this->credit_status;
    }

    /**
     * Check if customer is business type
     */
    public function isBusiness(): bool
    {
        return $this->customer_type === 'business';
    }

    /**
     * Check if customer has credit terms
     */
    public function hasCreditTerms(): bool
    {
        return $this->payment_terms_days > 0;
    }

    /**
     * Get available credit amount
     */
    public function getAvailableCredit(): float
    {
        if (!$this->isBusiness()) {
            return 0;
        }

        $currentOutstanding = $this->sales()
            ->where('payment_status', '!=', 'paid')
            ->sum('outstanding_amount');

        return max(0, $this->credit_limit - $currentOutstanding);
    }

    /**
     * Update customer statistics after a purchase
     */
    public function updateStatistics(float $amount): void
    {
        $this->increment('total_transactions');
        $this->increment('total_spent', $amount);
        $this->update(['last_purchase_date' => now()]);
    }

    /**
     * Calculate loyalty points based on amount spent
     */
    public function addLoyaltyPoints(float $amount): void
    {
        $pointsRate = match ($this->customer_tier) {
            'silver' => 0.02,    // 2% 
            'gold' => 0.03,      // 3%
            'platinum' => 0.05,  // 5%
            default => 0.01,     // 1% for regular
        };

        $points = $amount * $pointsRate;
        $this->increment('loyalty_points', $points);
    }

    /**
     * Upgrade customer tier based on total spent
     */
    public function checkTierUpgrade(): void
    {
        $tierThresholds = [
            'platinum' => 50000000, // 50M IDR
            'gold' => 20000000,     // 20M IDR  
            'silver' => 5000000,    // 5M IDR
        ];

        foreach ($tierThresholds as $tier => $threshold) {
            if ($this->total_spent >= $threshold && $this->customer_tier !== $tier) {
                $this->update(['customer_tier' => $tier]);
                break;
            }
        }
    }
}
