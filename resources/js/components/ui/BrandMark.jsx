import React from 'react';

export default function BrandMark({ compact = false }) {
    return (
        <div className={`brand-mark ${compact ? 'brand-mark--compact' : ''}`}>
            <span className="brand-mark__crown" aria-hidden="true">♛</span>
            <span><strong>Gaia’s RSVP</strong><small>Royal celebration manager</small></span>
        </div>
    );
}
