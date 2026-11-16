/** Joins class names, skipping empty ones: cx('a', on && 'b', '') → "a b". */
export default function cx(...classes) {
    return classes.filter(Boolean).join(' ');
}
