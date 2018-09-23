module Jekyll
    module TagHelpersFilter
        def sort_tags_by_posts_count(tags)
            max_posts_count_among_all_tags = tags.max_by { |k,v| v.size }[1].size
            return tags.map   { |k,v| [ k, v.size ] }
                       .sort_by { |x| [ max_posts_count_among_all_tags - x[1], x[0].downcase ] }
        end

        # @param [String] input
        # @return [String]
        def headings_increase_level(input)
            html = input.to_s

            for level in 6.downto(1) do
                html = html
                    .gsub("<h" + level.to_s, "<h" + (level + 1).to_s)
                    .gsub("</h" + level.to_s, "</h" + (level + 1).to_s)
            end

            return html
        end
    end
end

Liquid::Template.register_filter(Jekyll::TagHelpersFilter)
