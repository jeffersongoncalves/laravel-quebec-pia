<?php

namespace JeffersonGoncalves\QuebecPia\Enums;

enum AssessmentType: string
{
    /**
     * A project to acquire, develop or overhaul an information system or electronic service delivery
     * involving personal information (s. 3.3). Consult the person in charge from the outset.
     */
    case Project = 'project';

    /**
     * Before communicating personal information outside Quebec (s. 17): its sensitivity, the purposes,
     * the protection measures, including contractual ones, and the legal framework of the destination.
     */
    case OutsideQuebec = 'outside_quebec';

    /** Communication for study, research or statistics without consent (s. 21). */
    case Research = 'research';
}
