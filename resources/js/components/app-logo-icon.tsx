import type { SVGAttributes } from 'react';

/**
 * The O'qi AI mark: an open book. Filled with the current text colour.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            fill="currentColor"
            {...props}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path d="M2 5.5C2 4.67 2.67 4 3.5 4c3 0 5.8.8 7.5 2.2V20c-1.8-1.3-4.5-2-7.5-2-.83 0-1.5-.67-1.5-1.5v-11Z" />
            <path d="M22 5.5c0-.83-.67-1.5-1.5-1.5-3 0-5.8.8-7.5 2.2V20c1.8-1.3 4.5-2 7.5-2 .83 0 1.5-.67 1.5-1.5v-11Z" />
        </svg>
    );
}
