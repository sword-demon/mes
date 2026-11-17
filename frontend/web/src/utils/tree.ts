/**
 * Flatten a nested tree into a list, parents before their children.
 */
export function flattenTree<T extends { children: T[] }>(nodes: T[]): T[] {
    return nodes.flatMap((node) => [node, ...flattenTree(node.children)]);
}
